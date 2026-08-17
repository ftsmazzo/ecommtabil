<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\Redirect;
use App\Core\Request;
use App\Models\Cartao;
use App\Models\FidelidadeRegra;
use App\Services\CartaoService;

class FidelidadeRegraController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title" => "Regras de Fidelidade",
            "active_menu" => "fidelidade-regras",
            "page" => [
                "title" => "Regras de Fidelidade",
                "desc" => "Cadastre e gerencie as regras de fidelidade dos cartões",
            ],
        ]);
    }

    public function index(): void
    {
        $this->authorize("cartao_gerenciar");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cartões" => ["url" => $this->router->route("admin.cartao.index"), "current" => false],
                "Regras de Fidelidade" => ["url" => false, "current" => true],
            ],
        ]);

        $dados = FidelidadeRegra::orderBy("descricao")->get();
        $regraVigente = $this->findRegraVigente();
        $elegiveis = $regraVigente ? $this->countCartoesElegiveis($regraVigente) : 0;

        if ($regraVigente) {
            $regraVigente->gatilho_print = $this->gatilhoLabel(
                $regraVigente->quantidade_vendas ?? null,
                $regraVigente->valor_minimo_venda ?? null,
                $regraVigente->valor_acumulado_minimo ?? null,
                $regraVigente->tipo_valor_acumulado ?? null
            );
        }

        foreach ($dados as $regra) {
            $regra->gatilho_print = $this->gatilhoLabel(
                $regra->quantidade_vendas ?? null,
                $regra->valor_minimo_venda ?? null,
                $regra->valor_acumulado_minimo ?? null,
                $regra->tipo_valor_acumulado ?? null
            );
            $regra->saldo_print = "R$ " . number_format((float) ($regra->valor_saldo ?? 0), 2, ",", ".");
            $regra->periodo_print = $this->periodoLabel($regra->data_inicio ?? null, $regra->data_fim ?? null);
            $regra->ativo_print = (int) ($regra->ativo ?? 0) === 1
                ? '<span class="badge filled-outlined bg-success">Sim</span>'
                : '<span class="badge filled-outlined bg-secondary">Não</span>';
        }

        $permissao = [
            "cartao" => $this->auth->allow("cartao_gerenciar"),
            "gerenciar" => $this->auth->allow("cartao_gerenciar"),
            "inserir" => $this->auth->allow("cartao_inserir"),
            "editar" => $this->auth->allow("cartao_editar"),
            "excluir" => $this->auth->allow("cartao_excluir"),
        ];

        echo $this->view->render("admin/cartao/fidelidade-regra/index", [
            "dados" => $dados,
            "permissao" => $permissao,
            "pode_criar" => $regraVigente === null,
            "regra_vigente" => $regraVigente,
            "elegiveis" => $elegiveis,
        ]);
    }

    public function new(): void
    {
        $this->authorize("cartao_inserir");

        $regraVigente = $this->findRegraVigente();
        if ($regraVigente) {
            $this->message->warning("Já existe uma regra de fidelidade vigente no momento.");
            $this->router->redirect("admin.fidelidade.regra.index");
            return;
        }

        echo $this->view->render("admin/cartao/fidelidade-regra/form", [
            "csrf" => $this->csrf->generate(),
            "regra" => false,
            "url_action" => $this->router->route("admin.fidelidade.regra.insert"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("cartao_inserir");

        $payload = $this->validatedPayload($request);
        $payload["created_by"] = $this->user->uid;

        $this->ensureRegraVigenteDisponivel($payload);

        FidelidadeRegra::create($payload);

        $this->message->success("Regra de fidelidade cadastrada com sucesso");
        $this->router->redirect("admin.fidelidade.regra.index");
    }

    public function edit(Request $request): void
    {
        $this->authorize("cartao_editar");

        $data = new Data($request->all());
        $regra = FidelidadeRegra::find($data->id) ?: FidelidadeRegra::findByMd5($data->id);

        if (!$regra) {
            $this->message->warning("Regra de fidelidade não encontrada");
            $this->router->redirect("admin.fidelidade.regra.index");
            return;
        }

        echo $this->view->render("admin/cartao/fidelidade-regra/form", [
            "csrf" => $this->csrf->generate(),
            "regra" => $regra,
            "url_action" => $this->router->route("admin.fidelidade.regra.update"),
        ]);
    }

    public function update(Request $request): void
    {
        $this->authorize("cartao_editar");

        $data = new Data($request->all());
        $regra = FidelidadeRegra::find($data->id) ?: FidelidadeRegra::findByMd5($data->id);

        if (!$regra) {
            $this->message->warning("Regra de fidelidade não encontrada");
            Redirect::referer();
            return;
        }

        $payload = $this->validatedPayload($request);
        $payload["updated_by"] = $this->user->uid;

        $this->ensureRegraVigenteDisponivel($payload, (int) $regra->id);

        FidelidadeRegra::updateBy($regra->id, $payload);

        $this->message->success("Regra de fidelidade atualizada com sucesso");
        $this->router->redirect("admin.fidelidade.regra.index");
    }

    public function delete(Request $request): void
    {
        $this->authorize("cartao_excluir");

        $data = new Data($request->all());
        $regra = FidelidadeRegra::find($data->id) ?: FidelidadeRegra::findByMd5($data->id);

        if (!$regra) {
            $this->message->warning("Regra de fidelidade não encontrada");
            Redirect::referer();
            return;
        }

        FidelidadeRegra::updateBy($regra->id, [
            "trash" => 1,
            "deleted_by" => $this->user->uid,
            "deleted_at" => date("Y-m-d H:i:s"),
        ]);

        $this->message->success("Regra de fidelidade removida com sucesso");
        Redirect::referer();
    }

    public function aplicarBonus(Request $request): void
    {
        $this->authorize("cartao_editar");

        $data = new Data($request->all());
        $regra = FidelidadeRegra::find($data->id) ?: FidelidadeRegra::findByMd5($data->id);

        if (!$regra) {
            $this->message->warning("Regra de fidelidade não encontrada.");
            $this->router->redirect("admin.fidelidade.regra.index");
            return;
        }

        $valorBonus = (float) ($regra->valor_saldo ?? 0);
        if ($valorBonus <= 0 || !$this->regraTemGatilho($regra)) {
            $this->message->warning("Regra invalida para aplicação de bônus.");
            $this->router->redirect("admin.fidelidade.regra.index");
            return;
        }

        $cartoes = Cartao::where('ct.status', '=', 'ATIVO')->get();
        if (empty($cartoes)) {
            $this->message->info("Nenhum cartão elegivel para bônus no momento.");
            $this->router->redirect("admin.fidelidade.regra.index");
            return;
        }

        $service = new CartaoService();
        $totalCartoes = 0;
        $totalCreditos = 0;

        foreach ($cartoes as $cartao) {
            $cartaoAtual = Cartao::find((int) $cartao->id);
            if (!$cartaoAtual) {
                continue;
            }

            $creditosCartao = 0;
            while ($this->cartaoElegivelParaRegra($cartaoAtual, $regra)) {
                try {
                    $gatilhoUsado = $this->resolverGatilhoAtingido($cartaoAtual, $regra);
                    $descricao = $this->descricaoBonus($regra, $gatilhoUsado, true);

                    $service->aplicarFidelidade($cartaoAtual, $valorBonus, $this->user->uid, $descricao);
                    $this->consumirGatilhoCartao($cartaoAtual, $regra, $gatilhoUsado);
                    $cartaoAtual = Cartao::find((int) $cartao->id);
                    $creditosCartao++;
                    $totalCreditos++;
                } catch (\RuntimeException) {
                    break;
                }
            }

            if ($creditosCartao > 0) {
                $totalCartoes++;
            }
        }

        $this->message->success(
            "Bônus aplicado: {$totalCreditos} crédito(s) em {$totalCartoes} cartão(s)."
        );
        $this->router->redirect("admin.fidelidade.regra.index");
    }

    private function validatedPayload(Request $request): array
    {
        $data = new Data($request->all());

        $descricao = trim((string) ($data->descricao ?? ""));
        $quantidadeVendas = (int) ($data->quantidade_vendas ?? 0);
        $valorMinimoVenda = $this->parseMoney($data->valor_minimo_venda ?? null);
        $valorAcumuladoMinimo = $this->parseMoney($data->valor_acumulado_minimo ?? null);
        $tipoValorAcumulado = $this->normalizarTipoValorAcumulado($data->tipo_valor_acumulado ?? null);
        $valorSaldo = $this->parseMoney($data->valor_saldo ?? null);

        if ($descricao === "") {
            $this->message->warning("Informe a descrição da regra.");
            Redirect::referer();
            exit;
        }

        if ($quantidadeVendas <= 0 && $valorAcumuladoMinimo <= 0) {
            $this->message->warning("Informe pelo menos um gatilho: quantidade de vendas ou valor acumulado.");
            Redirect::referer();
            exit;
        }

        if ($valorSaldo <= 0) {
            $this->message->warning("Informe o saldo concedido da regra.");
            Redirect::referer();
            exit;
        }

        return [
            "descricao" => $descricao,
            "quantidade_vendas" => $quantidadeVendas > 0 ? $quantidadeVendas : null,
            "valor_minimo_venda" => $valorMinimoVenda > 0 ? $valorMinimoVenda : null,
            "valor_acumulado_minimo" => $valorAcumuladoMinimo > 0 ? $valorAcumuladoMinimo : null,
            "tipo_valor_acumulado" => $tipoValorAcumulado,
            "valor_saldo" => $valorSaldo,
            "data_inicio" => $this->parseDatetimeLocal($data->data_inicio),
            "data_fim" => $this->parseDatetimeLocal($data->data_fim),
            "ativo" => $data->contain("ativo") ? (int) $data->ativo : 0,
            "observacoes" => trim((string) ($data->observacoes ?? "")),
            "trash" => 0,
        ];
    }

    private function parseMoney(mixed $value): float
    {
        $raw = trim((string) ($value ?? ""));
        if ($raw === "") {
            return 0.0;
        }

        return round((float) str_replace(",", ".", str_replace(".", "", $raw)), 2);
    }

    private function parseDatetimeLocal(mixed $value): ?string
    {
        $str = trim((string) ($value ?? ""));
        if ($str === "") {
            return null;
        }

        $ts = strtotime(str_replace("T", " ", $str));
        return $ts !== false ? date("Y-m-d H:i:s", $ts) : null;
    }

    private function normalizarTipoValorAcumulado(mixed $value): string
    {
        return strtoupper(trim((string) ($value ?? ''))) === 'UNICO' ? 'UNICO' : 'CICLICO';
    }

    private function gatilhoValorEhUnico(object $regra): bool
    {
        return $this->normalizarTipoValorAcumulado($regra->tipo_valor_acumulado ?? null) === 'UNICO';
    }

    private function cartaoJaUsouGatilhoValorUnico(object $cartao, object $regra): bool
    {
        return (int) ($cartao->fidelidade_valor_unico_regra_id ?? 0) > 0
            && (int) ($cartao->fidelidade_valor_unico_regra_id ?? 0) === (int) ($regra->id ?? 0);
    }

    private function gatilhoLabel(
        mixed $quantidadeVendas,
        mixed $valorMinimoVenda,
        mixed $valorAcumuladoMinimo,
        mixed $tipoValorAcumulado = null
    ): string
    {
        $partes = [];
        $qtd = (int) ($quantidadeVendas ?? 0);
        $valorMinimo = round((float) ($valorMinimoVenda ?? 0), 2);
        $valor = round((float) ($valorAcumuladoMinimo ?? 0), 2);

        if ($qtd > 0) {
            $labelQtd = $qtd === 1 ? "1 venda" : "{$qtd} vendas";
            if ($valorMinimo > 0) {
                $labelQtd .= " de no mínimo R$ " . number_format($valorMinimo, 2, ",", ".");
            }
            $partes[] = $labelQtd;
        }

        if ($valor > 0) {
            $labelValor = "R$ " . number_format($valor, 2, ",", ".") . " acumulados";
            if ($this->normalizarTipoValorAcumulado($tipoValorAcumulado) === 'UNICO') {
                $labelValor .= " (uma unica vez)";
            }
            $partes[] = $labelValor;
        }

        if (empty($partes)) {
            return '<i class="text-black-50">Não definido</i>';
        }

        return implode(" OU ", $partes);
    }

    private function periodoLabel(?string $inicio, ?string $fim): string
    {
        if (empty($inicio) && empty($fim)) {
            return '<i class="text-black-50">Não definido</i>';
        }

        $inicioPrint = !empty($inicio) ? datetimebr((string) $inicio) : "Sem início";
        $fimPrint = !empty($fim) ? datetimebr((string) $fim) : "Sem fim";

        return $inicioPrint . " até " . $fimPrint;
    }

    private function findRegraVigente(?int $ignoreId = null)
    {
        $agora = date("Y-m-d H:i:s");

        $query = FidelidadeRegra::where("ativo", "=", 1)
            ->whereRaw("(data_inicio IS NULL OR data_inicio <= ?)", [$agora])
            ->whereRaw("(data_fim IS NULL OR data_fim >= ?)", [$agora])
            ->orderBy("id", "desc");

        if ($ignoreId !== null) {
            $query->where("id", "!=", $ignoreId);
        }

        return $query->first();
    }

    private function ensureRegraVigenteDisponivel(array $payload, ?int $ignoreId = null): void
    {
        if (!$this->payloadEstaVigenteAgora($payload)) {
            return;
        }

        $regraVigente = $this->findRegraVigente($ignoreId);
        if (!$regraVigente) {
            return;
        }

        $descricao = trim((string) ($regraVigente->descricao ?? ""));
        $sufixo = $descricao !== "" ? " ({$descricao})" : "";

        $this->message->warning("Já existe uma regra de fidelidade vigente no momento{$sufixo}.");
        Redirect::referer();
        exit;
    }

    private function payloadEstaVigenteAgora(array $payload): bool
    {
        if ((int) ($payload["ativo"] ?? 0) !== 1) {
            return false;
        }

        $agora = date("Y-m-d H:i:s");
        $inicio = $payload["data_inicio"] ?? null;
        $fim = $payload["data_fim"] ?? null;

        if ($inicio !== null && $inicio > $agora) {
            return false;
        }

        if ($fim !== null && $fim < $agora) {
            return false;
        }

        return true;
    }

    private function regraTemGatilho(object $regra): bool
    {
        return (int) ($regra->quantidade_vendas ?? 0) > 0
            || (float) ($regra->valor_acumulado_minimo ?? 0) > 0;
    }

    private function countCartoesElegiveis(object $regra): int
    {
        $cartoes = Cartao::where('ct.status', '=', 'ATIVO')->get();
        $total = 0;

        foreach ($cartoes as $cartao) {
            if ($this->cartaoElegivelParaRegra($cartao, $regra)) {
                $total++;
            }
        }

        return $total;
    }

    private function cartaoElegivelParaRegra(object $cartao, object $regra): bool
    {
        $gatilhoVendas = (int) ($regra->quantidade_vendas ?? 0);
        $gatilhoValor = round((float) ($regra->valor_acumulado_minimo ?? 0), 2);
        $acumulado = (int) ($cartao->acumulado ?? 0);
        $valorAcumulado = round((float) ($cartao->valor_acumulado ?? 0), 2);
        $gatilhoValorDisponivel = !$this->gatilhoValorEhUnico($regra)
            || !$this->cartaoJaUsouGatilhoValorUnico($cartao, $regra);

        $okVendas = $gatilhoVendas > 0 && $acumulado >= $gatilhoVendas;
        $okValor = $gatilhoValor > 0 && $gatilhoValorDisponivel && $valorAcumulado >= $gatilhoValor;

        return $okVendas || $okValor;
    }

    private function resolverGatilhoAtingido(object $cartao, object $regra): string
    {
        $gatilhoVendas = (int) ($regra->quantidade_vendas ?? 0);
        $gatilhoValor = round((float) ($regra->valor_acumulado_minimo ?? 0), 2);
        $acumulado = (int) ($cartao->acumulado ?? 0);
        $valorAcumulado = round((float) ($cartao->valor_acumulado ?? 0), 2);
        $gatilhoValorDisponivel = !$this->gatilhoValorEhUnico($regra)
            || !$this->cartaoJaUsouGatilhoValorUnico($cartao, $regra);

        $okVendas = $gatilhoVendas > 0 && $acumulado >= $gatilhoVendas;
        $okValor = $gatilhoValor > 0 && $gatilhoValorDisponivel && $valorAcumulado >= $gatilhoValor;

        if ($okVendas && !$okValor) {
            return 'vendas';
        }

        if ($okValor && !$okVendas) {
            return 'valor';
        }

        if ($okVendas && $okValor) {
            $ratioVendas = $gatilhoVendas > 0 ? $acumulado / $gatilhoVendas : PHP_FLOAT_MAX;
            $ratioValor = $gatilhoValor > 0 ? $valorAcumulado / $gatilhoValor : PHP_FLOAT_MAX;
            return $ratioVendas <= $ratioValor ? 'vendas' : 'valor';
        }

        return 'vendas';
    }

    private function consumirGatilhoCartao(object $cartao, object $regra, string $gatilhoUsado): void
    {
        $updates = [];

        if ($gatilhoUsado === 'valor') {
            $gatilhoValor = round((float) ($regra->valor_acumulado_minimo ?? 0), 2);
            $valorAtual = round((float) ($cartao->valor_acumulado ?? 0), 2);
            if ($this->gatilhoValorEhUnico($regra)) {
                $updates["fidelidade_valor_unico_regra_id"] = (int) ($regra->id ?? 0) ?: null;
            } else {
                $updates["valor_acumulado"] = max(0, round($valorAtual - $gatilhoValor, 2));
            }
        } else {
            $gatilhoVendas = (int) ($regra->quantidade_vendas ?? 0);
            $acumuladoAtual = (int) ($cartao->acumulado ?? 0);
            $updates["acumulado"] = max(0, $acumuladoAtual - $gatilhoVendas);
        }

        Cartao::updateBy((int) $cartao->id, $updates);
    }

    private function descricaoBonus(object $regra, string $gatilhoUsado, bool $emLote = false): string
    {
        $prefixo = $emLote ? "Bônus em lote de fidelidade" : "Bônus manual de fidelidade";

        if ($gatilhoUsado === 'valor') {
            $gatilhoValor = round((float) ($regra->valor_acumulado_minimo ?? 0), 2);
            $descricao = $prefixo . " por R$ " . number_format($gatilhoValor, 2, ",", ".") . " acumulados";

            if ($this->gatilhoValorEhUnico($regra)) {
                $descricao .= " uma unica vez";
            } else {
                $descricao .= " a cada meta";
            }

            return $descricao;
        }

        $gatilhoVendas = (int) ($regra->quantidade_vendas ?? 0);
        $descricao = $prefixo . " a cada {$gatilhoVendas} venda" . ($gatilhoVendas > 1 ? 's' : '');
        $valorMinimo = round((float) ($regra->valor_minimo_venda ?? 0), 2);
        if ($valorMinimo > 0) {
            $descricao .= " de no mínimo R$ " . number_format($valorMinimo, 2, ",", ".");
        }

        return $descricao;
    }
}
