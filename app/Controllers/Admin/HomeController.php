<?php
namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\ControllerAdmin;
use App\Core\DB;
use App\Core\Data;
use App\Core\Request;
use App\Enums\CartaoStatusEnum;
use App\Enums\MovimentacaoTipoEnum;
use App\Models\Cartao;
use App\Models\Configuracao;
use App\Models\Empresa;
use App\Models\Projeto;
use App\Models\ProjetoLancamento;
use App\Models\UsuarioProjetoRecente;
use App\Services\CartaoService;

class HomeController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData(
            [
                "title" => "Home",
                "page_title" => "Meu Painel",
                "active_menu" => "painel",
            ]
        );
    }

    public function index(Request $request): void
    {
        $this->view->addData([
            "breadcrumb" => [
                "Painel" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Painel",
                "desc"  => "Projetos e dados já importados no DRE, DFC e BP",
            ],
        ]);

        $totais = ProjetoLancamento::totaisGerais();
        $qtdEmpresas = count(Empresa::get());
        $qtdProjetos = count(Projeto::get());

        $projetos = Projeto::leftJoin("empresa as e", "p.id_empresa", "=", "e.id")
            ->select("p.*", "e.razao as empresa_razao", "e.nome as empresa_nome")
            ->orderBy("p.nome")
            ->get();

        foreach ($projetos as $projeto) {
            $projeto->empresa_print = trim((string) ($projeto->empresa_razao ?: $projeto->empresa_nome)) ?: "-";
            $projeto->abrir_url     = $this->router->route("admin.projeto.abrir", ["id" => $projeto->id]);
            $projeto->resumo        = ProjetoLancamento::resumoPorProjeto((int) $projeto->id);
        }

        $recentes = UsuarioProjetoRecente::recentesPorUsuario((int) ($this->user->uid ?? 0), 6);
        foreach ($recentes as &$rec) {
            $rec["url"] = $this->router->route("admin.projeto.abrir", ["id" => $rec["id"]]);
        }
        unset($rec);

        echo $this->view->render("admin/home/index", [
            "vapidKey"    => Config::get("push.publicKey"),
            "userToken"   => $this->user->token,
            "csrf"        => $this->csrf->generate(),
            "totais"      => $totais,
            "qtdEmpresas" => (int) $qtdEmpresas,
            "qtdProjetos" => (int) $qtdProjetos,
            "projetos"    => $projetos,
            "recentes"    => $recentes,
        ]);
    }

    public function atalhoRecarga(Request $request): void
    {
        $this->authorize("movimentacao_inserir");

        $data = new Data($request->all());
        $identificador = strtoupper(trim((string) ($data->codigo ?? '')));
        $valor = $this->parseValor((string) ($data->valor ?? ''));
        $clienteObrigatorio = Configuracao::getValue("cartao_cliente_obrigatorio", "1") === "1";
        $entradaNumerica = ctype_digit(only_numbers($identificador)) && only_numbers($identificador) !== '';

        if ($identificador === '') {
            $this->message->warning("Informe o numero do cartao.");
            $this->router->redirect("admin.home");
            return;
        }

        if ($valor <= 0) {
            $this->message->warning("Informe um valor de recarga valido.");
            $this->router->redirect("admin.home");
            return;
        }

        $service = new CartaoService();
        $cartao = $service->buscarPorCodigo($identificador);

        if (!$cartao) {
            if ($clienteObrigatorio) {
                $this->message->warning("Cartao nao encontrado. Conclua o cadastro para seguir com a recarga.");
                $this->router->redirect("admin.cartao.novo", $entradaNumerica
                    ? ["codigo" => $identificador]
                    : ["token_nfc" => $identificador]);
                return;
            }

            if (!$entradaNumerica) {
                $this->message->warning("Cartao nao encontrado. Conclua o cadastro para seguir com a recarga.");
                $this->router->redirect("admin.cartao.novo", ["token_nfc" => $identificador]);
                return;
            }

            $codigoUnico = $this->normalizeCodigoUnico($identificador);
            if ($codigoUnico === '' || $codigoUnico === '000000') {
                $this->message->warning("Informe um numero de cartao valido.");
                $this->router->redirect("admin.home");
                return;
            }

            if ($this->codigoJaExiste($codigoUnico)) {
                $cartao = $service->buscarPorCodigo($codigoUnico);
            } else {
                $cartao = Cartao::create([
                    "id_cliente" => null,
                    "codigo_unico" => $codigoUnico,
                    "token_nfc" => null,
                    "validade" => null,
                    "observacoes" => "Criado automaticamente pelo atalho de recarga da home.",
                    "data_emissao" => date("Y-m-d H:i:s"),
                    "data_ativacao" => date("Y-m-d H:i:s"),
                    "status" => CartaoStatusEnum::ATIVO->value,
                    "created_by" => $this->user->uid,
                ]);
            }

            if (!$cartao) {
                $this->message->warning("Nao foi possivel localizar ou criar o cartao.");
                $this->router->redirect("admin.home");
                return;
            }
        }

        $recargaMinima = (float) Configuracao::getValue('recarga_minima', 0);
        if ($recargaMinima > 0 && $valor < $recargaMinima) {
            $this->message->warning(
                "A recarga minima permitida e de R$ " . number_format($recargaMinima, 2, ',', '.') . "."
            );
            $this->router->redirect("admin.home");
            return;
        }

        try {
            $service->recarregar(
                cartao: Cartao::find((int) ($cartao->id ?? 0)) ?: $cartao,
                valor: $valor,
                idOperador: $this->user->uid,
                origem: 'admin',
                createdBy: $this->user->uid
            );

            $this->message->success(
                "Recarga de R$ " . number_format($valor, 2, ',', '.') . " realizada com sucesso."
            );
            $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
            return;
        } catch (\RuntimeException $e) {
            $this->message->warning($e->getMessage());
        }

        $this->router->redirect("admin.home");
    }

    private function sanitizePeriodo(string $periodo): string
    {
        $validos = array_keys($this->getPeriodoOptions());
        return in_array($periodo, $validos, true) ? $periodo : 'week';
    }

    private function getPeriodoOptions(): array
    {
        return [
            'today' => 'Hoje',
            'week'  => 'Esta semana',
            'month' => 'Este mês',
            'year'  => 'Este ano',
        ];
    }

    private function resolvePeriodoRange(string $periodo): object
    {
        $hoje = new \DateTimeImmutable('today');

        return match ($periodo) {
            'today' => (object) [
                'inicio' => $hoje->format('Y-m-d'),
                'fim'    => $hoje->format('Y-m-d'),
                'label'  => 'Hoje',
            ],
            'month' => (object) [
                'inicio' => $hoje->modify('first day of this month')->format('Y-m-d'),
                'fim'    => $hoje->modify('last day of this month')->format('Y-m-d'),
                'label'  => 'Este mês',
            ],
            'year' => (object) [
                'inicio' => $hoje->setDate((int) $hoje->format('Y'), 1, 1)->format('Y-m-d'),
                'fim'    => $hoje->format('Y-m-d'),
                'label'  => 'Este ano',
            ],
            default => (object) [
                'inicio' => $hoje->modify('monday this week')->modify('-1 day')->format('Y-m-d'),
                'fim'    => $hoje->modify('monday this week')->modify('+6 days')->format('Y-m-d'),
                'label'  => 'Esta semana',
            ],
        };
    }

    private function getCartoesTotal(): int
    {
        return (int) Cartao::count();
    }

    private function parseValor(string $raw): float
    {
        $normalized = str_replace([".", ","], ["", "."], trim($raw));
        return round((float) $normalized, 2);
    }

    private function normalizeCodigoUnico(string $codigo): string
    {
        $codigo = only_numbers(trim($codigo));

        if ($codigo === '') {
            return '';
        }

        return str_pad($codigo, 6, '0', STR_PAD_LEFT);
    }

    private function codigoJaExiste(string $codigo): bool
    {
        $service = new CartaoService();
        return $service->buscarPorCodigo($codigo) !== null;
    }

    private function getCartoesPeriodo(string $inicio, string $fim): int
    {
        return (int) Cartao::whereRaw('DATE(ct.created_at) BETWEEN ? AND ?', [$inicio, $fim])->count();
    }

    private function getResumoTipos(array $tipos, string $inicio, string $fim): object
    {
        $placeholders = implode(', ', array_fill(0, count($tipos), '?'));
        $bindings     = array_merge($tipos, [$inicio, $fim]);

        $rows = DB::run(
            "SELECT
                COUNT(*) AS quantidade,
                COALESCE(SUM(valor), 0) AS total
             FROM movimentacao
             WHERE tipo IN ({$placeholders})
               AND DATE(created_at) BETWEEN ? AND ?",
            $bindings
        );

        $row = $rows[0] ?? ['quantidade' => 0, 'total' => 0];

        return (object) [
            'quantidade' => (int) ($row['quantidade'] ?? 0),
            'total'      => round((float) ($row['total'] ?? 0), 2),
        ];
    }

    private function getGraficoMensal(string $inicio, string $fim): array
    {
        $rows = DB::run(
            "SELECT
                DATE_FORMAT(created_at, '%Y-%m') AS mes,
                SUM(CASE WHEN tipo IN (?, ?) THEN valor ELSE 0 END) AS recarga_total,
                SUM(CASE WHEN tipo = ? THEN valor ELSE 0 END) AS cashback_total,
                SUM(CASE WHEN tipo = ? THEN valor ELSE 0 END) AS fidelidade_total
             FROM movimentacao
             WHERE DATE(created_at) BETWEEN ? AND ?
               AND tipo IN (?, ?, ?, ?)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY mes ASC",
            [
                MovimentacaoTipoEnum::RECARGA->value,
                MovimentacaoTipoEnum::CARGA_INICIAL->value,
                MovimentacaoTipoEnum::CASHBACK->value,
                MovimentacaoTipoEnum::FIDELIDADE->value,
                $inicio,
                $fim,
                MovimentacaoTipoEnum::RECARGA->value,
                MovimentacaoTipoEnum::CARGA_INICIAL->value,
                MovimentacaoTipoEnum::CASHBACK->value,
                MovimentacaoTipoEnum::FIDELIDADE->value,
            ]
        );

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['mes']] = [
                'recarga'    => round((float) ($row['recarga_total'] ?? 0), 2),
                'cashback'   => round((float) ($row['cashback_total'] ?? 0), 2),
                'fidelidade' => round((float) ($row['fidelidade_total'] ?? 0), 2),
            ];
        }

        $ano    = (int) (new \DateTimeImmutable($inicio))->format('Y');
        $labels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $series = [];

        for ($m = 1; $m <= 12; $m++) {
            $key      = sprintf('%d-%02d', $ano, $m);
            $valores  = $indexed[$key] ?? ['recarga' => 0, 'cashback' => 0, 'fidelidade' => 0];
            $series[] = [
                'dia'        => $key,
                'label'      => $labels[$m - 1],
                'recarga'    => $valores['recarga'],
                'cashback'   => $valores['cashback'],
                'fidelidade' => $valores['fidelidade'],
            ];
        }

        return $series;
    }

    private function getGraficoDiario(string $inicio, string $fim): array
    {
        $rows = DB::run(
            "SELECT
                DATE(created_at) AS dia,
                SUM(CASE WHEN tipo IN (?, ?) THEN valor ELSE 0 END) AS recarga_total,
                SUM(CASE WHEN tipo = ? THEN valor ELSE 0 END) AS cashback_total,
                SUM(CASE WHEN tipo = ? THEN valor ELSE 0 END) AS fidelidade_total
             FROM movimentacao
             WHERE DATE(created_at) BETWEEN ? AND ?
               AND tipo IN (?, ?, ?, ?)
             GROUP BY DATE(created_at)
             ORDER BY DATE(created_at) ASC",
            [
                MovimentacaoTipoEnum::RECARGA->value,
                MovimentacaoTipoEnum::CARGA_INICIAL->value,
                MovimentacaoTipoEnum::CASHBACK->value,
                MovimentacaoTipoEnum::FIDELIDADE->value,
                $inicio,
                $fim,
                MovimentacaoTipoEnum::RECARGA->value,
                MovimentacaoTipoEnum::CARGA_INICIAL->value,
                MovimentacaoTipoEnum::CASHBACK->value,
                MovimentacaoTipoEnum::FIDELIDADE->value,
            ]
        );

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(string) $row['dia']] = [
                'recarga'    => round((float) ($row['recarga_total'] ?? 0), 2),
                'cashback'   => round((float) ($row['cashback_total'] ?? 0), 2),
                'fidelidade' => round((float) ($row['fidelidade_total'] ?? 0), 2),
            ];
        }

        $cursor = new \DateTimeImmutable($inicio);
        $fimObj = new \DateTimeImmutable($fim);
        $series = [];

        while ($cursor <= $fimObj) {
            $dia = $cursor->format('Y-m-d');
            $valores = $indexed[$dia] ?? ['recarga' => 0, 'cashback' => 0, 'fidelidade' => 0];

            $series[] = [
                'dia'        => $dia,
                'label'      => $cursor->format('d/m'),
                'recarga'    => $valores['recarga'],
                'cashback'   => $valores['cashback'],
                'fidelidade' => $valores['fidelidade'],
            ];

            $cursor = $cursor->modify('+1 day');
        }

        return $series;
    }
}
