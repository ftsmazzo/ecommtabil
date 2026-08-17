<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\Request;
use App\Enums\MovimentacaoTipoEnum;
use App\Models\Cartao;
use App\Models\Movimentacao;

class RelatorioController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title" => "Relatórios",
            "active_menu" => "cartoes",
            "page" => [
                "title" => "Relatórios",
                "desc" => "Consulte os relatórios operacionais dos cartões",
            ],
        ]);
    }

    public function relatorioBonus(Request $request): void
    {
        $this->authorize("relatorio_bonus");

        $data = new Data($request->all());
        $dataInicial = trim((string) ($data->data_inicial ?? ''));
        $dataFinal = trim((string) ($data->data_final ?? ''));
        $tipoFiltro = strtoupper(trim((string) ($data->tipo ?? '')));

        $query = Movimentacao::leftJoin("cartao as ct", "mv.id_cartao", "=", "ct.id")
            ->leftJoin("cliente as cl", "ct.id_cliente", "=", "cl.id")
            ->select(
                "mv.*",
                "ct.codigo_unico as cartao_codigo",
                "cl.razao as cliente_razao",
                "cl.nome as cliente_nome"
            )
            ->whereRaw(
                "(mv.tipo = ? OR mv.tipo = ?)",
                [MovimentacaoTipoEnum::CASHBACK->value, MovimentacaoTipoEnum::FIDELIDADE->value]
            );

        if ($dataInicial !== '') {
            $query->whereRaw("DATE(mv.created_at) >= ?", [$dataInicial]);
        }

        if ($dataFinal !== '') {
            $query->whereRaw("DATE(mv.created_at) <= ?", [$dataFinal]);
        }

        if (in_array($tipoFiltro, [MovimentacaoTipoEnum::CASHBACK->value, MovimentacaoTipoEnum::FIDELIDADE->value], true)) {
            $query->where("mv.tipo", "=", $tipoFiltro);
        }

        $movimentacoes = $query
            ->orderBy("mv.created_at", "desc")
            ->orderBy("mv.id", "desc")
            ->get();

        $resumo = (object) [
            "cashback_total" => 0.0,
            "cashback_quantidade" => 0,
            "fidelidade_total" => 0.0,
            "fidelidade_quantidade" => 0,
            "total_geral" => 0.0,
            "quantidade_geral" => 0,
        ];

        foreach ($movimentacoes as $mov) {
            $valor = round((float) ($mov->valor ?? 0), 2);
            $tipo = (string) ($mov->tipo ?? '');

            $mov->cliente_print = trim((string) ($mov->cliente_razao ?: $mov->cliente_nome)) ?: "-";
            $mov->cartao_print = trim((string) ($mov->cartao_codigo ?? "")) ?: "-";
            $mov->data_print = !empty($mov->created_at) ? datetimebr((string) $mov->created_at) : "-";
            $mov->valor_print = "R$ " . number_format($valor, 2, ",", ".");
            $mov->descricao_print = trim((string) ($mov->descricao ?? "")) ?: "-";

            if ($tipo === MovimentacaoTipoEnum::CASHBACK->value) {
                $mov->tipo_print = '<span class="badge filled-outlined bg-success">Cashback</span>';
                $resumo->cashback_total = round($resumo->cashback_total + $valor, 2);
                $resumo->cashback_quantidade++;
            } else {
                $mov->tipo_print = '<span class="badge filled-outlined bg-info">Fidelidade</span>';
                $resumo->fidelidade_total = round($resumo->fidelidade_total + $valor, 2);
                $resumo->fidelidade_quantidade++;
            }
        }

        $resumo->total_geral = round($resumo->cashback_total + $resumo->fidelidade_total, 2);
        $resumo->quantidade_geral = $resumo->cashback_quantidade + $resumo->fidelidade_quantidade;

        $this->view->addData([
            "active_menu" => "cartao-relatorio-bonus",
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cartões" => ["url" => $this->router->route("admin.cartao.index"), "current" => false],
                "Relatório de Bônus" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Relatório de Bônus",
                "desc" => "Consulte o que foi concedido em cashback e fidelidade no período.",
            ],
        ]);

        echo $this->view->render("admin/cartao/relatorio-bonus", [
            "movimentacoes" => $movimentacoes,
            "resumo" => $resumo,
            "filtros" => (object) [
                "data_inicial" => $dataInicial,
                "data_final" => $dataFinal,
                "tipo" => $tipoFiltro,
            ],
        ]);
    }

    public function relatorioRecargas(Request $request): void
    {
        $this->authorize("relatorio_recerga");

        $data = new Data($request->all());
        $dataInicial = trim((string) ($data->data_inicial ?? ''));
        $dataFinal = trim((string) ($data->data_final ?? ''));

        $query = Movimentacao::leftJoin("cartao as ct", "mv.id_cartao", "=", "ct.id")
            ->leftJoin("cliente as cl", "ct.id_cliente", "=", "cl.id")
            ->select(
                "mv.*",
                "ct.codigo_unico as cartao_codigo",
                "cl.razao as cliente_razao",
                "cl.nome as cliente_nome"
            )
            ->whereRaw(
                "(mv.tipo = ? OR mv.tipo = ?)",
                [MovimentacaoTipoEnum::RECARGA->value, MovimentacaoTipoEnum::CARGA_INICIAL->value]
            );

        if ($dataInicial !== '') {
            $query->whereRaw("DATE(mv.created_at) >= ?", [$dataInicial]);
        }

        if ($dataFinal !== '') {
            $query->whereRaw("DATE(mv.created_at) <= ?", [$dataFinal]);
        }

        $movimentacoes = $query
            ->orderBy("mv.created_at", "desc")
            ->orderBy("mv.id", "desc")
            ->get();

        $resumo = (object) [
            "total" => 0.0,
            "quantidade" => 0,
            "ticket_medio" => 0.0,
            "carga_inicial_total" => 0.0,
            "carga_inicial_quantidade" => 0,
            "recarga_total" => 0.0,
            "recarga_quantidade" => 0,
        ];

        foreach ($movimentacoes as $mov) {
            $valor = round((float) ($mov->valor ?? 0), 2);
            $tipo = (string) ($mov->tipo ?? '');

            $mov->cliente_print = trim((string) ($mov->cliente_razao ?: $mov->cliente_nome)) ?: "-";
            $mov->cartao_print = trim((string) ($mov->cartao_codigo ?? "")) ?: "-";
            $mov->data_print = !empty($mov->created_at) ? datetimebr((string) $mov->created_at) : "-";
            $mov->valor_print = "R$ " . number_format($valor, 2, ",", ".");
            $mov->descricao_print = trim((string) ($mov->descricao ?? "")) ?: "-";
            $mov->tipo_print = $tipo === MovimentacaoTipoEnum::CARGA_INICIAL->value
                ? '<span class="badge filled-outlined bg-info">Carga Inicial</span>'
                : '<span class="badge filled-outlined bg-success">Recarga</span>';

            $resumo->total = round($resumo->total + $valor, 2);
            $resumo->quantidade++;

            if ($tipo === MovimentacaoTipoEnum::CARGA_INICIAL->value) {
                $resumo->carga_inicial_total = round($resumo->carga_inicial_total + $valor, 2);
                $resumo->carga_inicial_quantidade++;
            } else {
                $resumo->recarga_total = round($resumo->recarga_total + $valor, 2);
                $resumo->recarga_quantidade++;
            }
        }

        if ($resumo->quantidade > 0) {
            $resumo->ticket_medio = round($resumo->total / $resumo->quantidade, 2);
        }

        $this->view->addData([
            "active_menu" => "cartao-relatorio-recargas",
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cartões" => ["url" => $this->router->route("admin.cartao.index"), "current" => false],
                "Relatório de Recargas" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Relatório de Recargas",
                "desc" => "Consulte as recargas realizadas no período.",
            ],
        ]);

        echo $this->view->render("admin/cartao/relatorio-recargas", [
            "movimentacoes" => $movimentacoes,
            "resumo" => $resumo,
            "filtros" => (object) [
                "data_inicial" => $dataInicial,
                "data_final" => $dataFinal,
            ],
        ]);
    }

    public function relatorioGastos(Request $request): void
    {
        $this->authorize("relatorio_gasto");

        $data = new Data($request->all());
        $dataInicial = trim((string) ($data->data_inicial ?? ''));
        $dataFinal = trim((string) ($data->data_final ?? ''));

        $query = Movimentacao::leftJoin("cartao as ct", "mv.id_cartao", "=", "ct.id")
            ->leftJoin("cliente as cl", "ct.id_cliente", "=", "cl.id")
            ->select(
                "mv.id",
                "mv.id_cartao",
                "mv.valor",
                "mv.created_at",
                "ct.codigo_unico as cartao_codigo",
                "ct.id_cliente",
                "cl.razao as cliente_razao",
                "cl.nome as cliente_nome"
            )
            ->where("mv.tipo", "=", MovimentacaoTipoEnum::DEBITO->value)
            ->where("mv.referencia_tipo", "=", "venda");

        if ($dataInicial !== '') {
            $query->whereRaw("DATE(mv.created_at) >= ?", [$dataInicial]);
        }

        if ($dataFinal !== '') {
            $query->whereRaw("DATE(mv.created_at) <= ?", [$dataFinal]);
        }

        $movimentacoes = $query
            ->orderBy("mv.created_at", "asc")
            ->orderBy("mv.id", "asc")
            ->get();

        $linhas = [];
        $resumo = (object) [
            "total_gasto" => 0.0,
            "quantidade_gastos" => 0,
            "ticket_medio_geral" => 0.0,
            "cartoes_com_gasto" => 0,
        ];

        foreach ($movimentacoes as $mov) {
            $idCartao = (int) ($mov->id_cartao ?? 0);
            if ($idCartao <= 0) {
                continue;
            }

            if (!isset($linhas[$idCartao])) {
                $linhas[$idCartao] = (object) [
                    "id_cartao" => $idCartao,
                    "id_cliente" => (int) ($mov->id_cliente ?? 0),
                    "cartao_print" => trim((string) ($mov->cartao_codigo ?? "")) ?: "-",
                    "cliente_print" => trim((string) (($mov->cliente_razao ?? '') ?: ($mov->cliente_nome ?? ''))) ?: "-",
                    "quantidade_gastos" => 0,
                    "total_gasto" => 0.0,
                    "ticket_medio" => 0.0,
                    "primeiro_gasto" => null,
                    "ultimo_gasto" => null,
                    "frequencia_print" => "-",
                    "hub_url" => $this->router->route("admin.cartao.hub", ["id" => $idCartao]),
                ];
            }

            $valor = round((float) ($mov->valor ?? 0), 2);
            $linha = $linhas[$idCartao];
            $linha->quantidade_gastos++;
            $linha->total_gasto = round($linha->total_gasto + $valor, 2);

            $dataMov = !empty($mov->created_at) ? (string) $mov->created_at : null;
            if ($dataMov !== null) {
                if ($linha->primeiro_gasto === null || strtotime($dataMov) < strtotime((string) $linha->primeiro_gasto)) {
                    $linha->primeiro_gasto = $dataMov;
                }
                if ($linha->ultimo_gasto === null || strtotime($dataMov) > strtotime((string) $linha->ultimo_gasto)) {
                    $linha->ultimo_gasto = $dataMov;
                }
            }

            $linhas[$idCartao] = $linha;
            $resumo->total_gasto = round($resumo->total_gasto + $valor, 2);
            $resumo->quantidade_gastos++;
        }

        $linhas = array_values($linhas);
        foreach ($linhas as $linha) {
            if ($linha->quantidade_gastos > 0) {
                $linha->ticket_medio = round($linha->total_gasto / $linha->quantidade_gastos, 2);
            }

            $linha->total_gasto_print = "R$ " . number_format($linha->total_gasto, 2, ",", ".");
            $linha->ticket_medio_print = "R$ " . number_format($linha->ticket_medio, 2, ",", ".");
            $linha->ultimo_gasto_print = !empty($linha->ultimo_gasto) ? datetimebr((string) $linha->ultimo_gasto) : "-";
            $linha->frequencia_print = $this->formatarFrequenciaGastos(
                (int) $linha->quantidade_gastos,
                $linha->primeiro_gasto,
                $linha->ultimo_gasto
            );
        }

        usort($linhas, function ($a, $b) {
            return ($b->total_gasto <=> $a->total_gasto)
                ?: ((int) $b->quantidade_gastos <=> (int) $a->quantidade_gastos)
                ?: ((int) $b->id_cartao <=> (int) $a->id_cartao);
        });

        $resumo->cartoes_com_gasto = count($linhas);
        if ($resumo->quantidade_gastos > 0) {
            $resumo->ticket_medio_geral = round($resumo->total_gasto / $resumo->quantidade_gastos, 2);
        }

        $this->view->addData([
            "active_menu" => "cartao-relatorio-gastos",
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cartões" => ["url" => $this->router->route("admin.cartao.index"), "current" => false],
                "Relatório de Gastos" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Relatório de Gastos",
                "desc" => "Consulte os gastos consolidados por cartão no período.",
            ],
        ]);

        echo $this->view->render("admin/cartao/relatorio-gastos", [
            "linhas" => $linhas,
            "resumo" => $resumo,
            "filtros" => (object) [
                "data_inicial" => $dataInicial,
                "data_final" => $dataFinal,
            ],
        ]);
    }

    public function relatorioTops(Request $request): void
    {
        $this->authorize("relatorio_ranking");

        $topGastos = Cartao::leftJoin("cliente as cl", "ct.id_cliente", "=", "cl.id")
            ->select(
                "ct.id",
                "ct.codigo_unico",
                "ct.total_gasto",
                "ct.total_vendas",
                "ct.saldo",
                "ct.id_cliente",
                "cl.razao as cliente_razao",
                "cl.nome as cliente_nome"
            )
            ->whereRaw("COALESCE(ct.total_gasto, 0) > 0")
            ->orderBy("ct.total_gasto", "desc")
            ->orderBy("ct.id", "desc")
            ->limit(5)
            ->get();

        $cartoesBaseSaldo = Cartao::leftJoin("cliente as cl", "ct.id_cliente", "=", "cl.id")
            ->select(
                "ct.id",
                "ct.codigo_unico",
                "ct.saldo",
                "ct.total_gasto",
                "ct.total_vendas",
                "ct.id_cliente",
                "cl.razao as cliente_razao",
                "cl.nome as cliente_nome"
            )
            ->get();

        $movimentacoesSaldo = Movimentacao::select(
            "mv.id_cartao",
            "mv.valor",
            "mv.sentido",
            "mv.tipo",
            "mv.referencia_tipo"
        )->get();

        $saldoCalculadoPorCartao = [];
        foreach ($movimentacoesSaldo as $mov) {
            $idCartao = (int) ($mov->id_cartao ?? 0);
            if ($idCartao <= 0) {
                continue;
            }

            if (!isset($saldoCalculadoPorCartao[$idCartao])) {
                $saldoCalculadoPorCartao[$idCartao] = 0.0;
            }

            $valor = (float) ($mov->valor ?? 0);
            $isGasto = (string) ($mov->tipo ?? '') === MovimentacaoTipoEnum::DEBITO->value
                && (string) ($mov->referencia_tipo ?? '') === 'venda';

            if ($isGasto) {
                continue;
            }

            if (strtoupper((string) ($mov->sentido ?? '')) === 'DEBITO') {
                $saldoCalculadoPorCartao[$idCartao] = round($saldoCalculadoPorCartao[$idCartao] - $valor, 2);
            } else {
                $saldoCalculadoPorCartao[$idCartao] = round($saldoCalculadoPorCartao[$idCartao] + $valor, 2);
            }
        }

        $topSaldo = [];
        foreach ($cartoesBaseSaldo as $item) {
            $saldoCalculado = $saldoCalculadoPorCartao[(int) ($item->id ?? 0)] ?? (float) ($item->saldo ?? 0);
            $item->saldo = round((float) $saldoCalculado, 2);
            $topSaldo[] = $item;
        }

        usort($topSaldo, function ($a, $b) {
            return ((float) ($b->saldo ?? 0) <=> (float) ($a->saldo ?? 0))
                ?: ((int) ($b->id ?? 0) <=> (int) ($a->id ?? 0));
        });
        $topSaldo = array_slice($topSaldo, 0, 5);

        $movimentacoesRecarga = Movimentacao::leftJoin("cartao as ct", "mv.id_cartao", "=", "ct.id")
            ->leftJoin("cliente as cl", "ct.id_cliente", "=", "cl.id")
            ->select(
                "mv.id_cartao",
                "mv.tipo",
                "mv.valor",
                "ct.codigo_unico as cartao_codigo",
                "ct.id_cliente",
                "cl.razao as cliente_razao",
                "cl.nome as cliente_nome"
            )
            ->whereRaw("(mv.tipo = ? OR mv.tipo = ?)", [
                MovimentacaoTipoEnum::RECARGA->value,
                MovimentacaoTipoEnum::CARGA_INICIAL->value,
            ])
            ->get();

        $recargasPorCartao = [];
        foreach ($movimentacoesRecarga as $mov) {
            $idCartao = (int) ($mov->id_cartao ?? 0);
            if ($idCartao <= 0) {
                continue;
            }

            if (!isset($recargasPorCartao[$idCartao])) {
                $recargasPorCartao[$idCartao] = (object) [
                    "id" => $idCartao,
                    "codigo_print" => trim((string) ($mov->cartao_codigo ?? "")) ?: "-",
                    "id_cliente" => (int) ($mov->id_cliente ?? 0),
                    "cliente_print" => trim((string) (($mov->cliente_razao ?? '') ?: ($mov->cliente_nome ?? ''))) ?: "-",
                    "recarga_total" => 0.0,
                ];
            }

            $recargasPorCartao[$idCartao]->recarga_total = round(
                (float) $recargasPorCartao[$idCartao]->recarga_total + (float) ($mov->valor ?? 0),
                2
            );
        }

        $topRecargas = array_values($recargasPorCartao);
        usort($topRecargas, function ($a, $b) {
            return ($b->recarga_total <=> $a->recarga_total) ?: ((int) $b->id <=> (int) $a->id);
        });
        $topRecargas = array_values(array_filter($topRecargas, function ($item) {
            return (float) ($item->recarga_total ?? 0) > 0;
        }));
        $topRecargas = array_slice($topRecargas, 0, 5);

        $topBeneficiado = Cartao::leftJoin("cliente as cl", "ct.id_cliente", "=", "cl.id")
            ->select(
                "ct.id",
                "ct.codigo_unico",
                "ct.id_cliente",
                "cl.razao as cliente_razao",
                "cl.nome as cliente_nome"
            )
            ->whereRaw("ct.id IS NOT NULL")
            ->get();

        foreach ($topGastos as $item) {
            $item->cliente_print = trim((string) ($item->cliente_razao ?: $item->cliente_nome)) ?: "-";
            $item->codigo_print = trim((string) ($item->codigo_unico ?? "")) ?: "-";
            $item->total_gasto_print = "R$ " . number_format((float) ($item->total_gasto ?? 0), 2, ",", ".");
            $item->hub_url = $this->router->route("admin.cartao.hub", ["id" => $item->id]);
        }

        foreach ($topSaldo as $item) {
            $item->cliente_print = trim((string) ($item->cliente_razao ?: $item->cliente_nome)) ?: "-";
            $item->codigo_print = trim((string) ($item->codigo_unico ?? "")) ?: "-";
            $item->saldo_print = "R$ " . number_format((float) ($item->saldo ?? 0), 2, ",", ".");
            $item->hub_url = $this->router->route("admin.cartao.hub", ["id" => $item->id]);
        }

        foreach ($topRecargas as $item) {
            $item->recarga_total_print = "R$ " . number_format((float) ($item->recarga_total ?? 0), 2, ",", ".");
            $item->hub_url = $this->router->route("admin.cartao.hub", ["id" => $item->id]);
        }

        foreach ($topBeneficiado as $item) {
            $item->cliente_print = trim((string) ($item->cliente_razao ?: $item->cliente_nome)) ?: "-";
            $item->codigo_print = trim((string) ($item->codigo_unico ?? "")) ?: "-";

            $totais = Movimentacao::where("mv.id_cartao", "=", (int) $item->id)
                ->whereRaw("(mv.tipo = ? OR mv.tipo = ?)", [
                    MovimentacaoTipoEnum::CASHBACK->value,
                    MovimentacaoTipoEnum::FIDELIDADE->value,
                ])
                ->get();

            $cashback = 0.0;
            $fidelidade = 0.0;
            foreach ($totais as $mov) {
                $valor = (float) ($mov->valor ?? 0);
                if ((string) ($mov->tipo ?? '') === MovimentacaoTipoEnum::CASHBACK->value) {
                    $cashback = round($cashback + $valor, 2);
                } else {
                    $fidelidade = round($fidelidade + $valor, 2);
                }
            }

            $item->beneficio_total = round($cashback + $fidelidade, 2);
            $item->beneficio_total_print = "R$ " . number_format($item->beneficio_total, 2, ",", ".");
            $item->cashback_print = "R$ " . number_format($cashback, 2, ",", ".");
            $item->fidelidade_print = "R$ " . number_format($fidelidade, 2, ",", ".");
            $item->hub_url = $this->router->route("admin.cartao.hub", ["id" => $item->id]);
        }

        usort($topBeneficiado, function ($a, $b) {
            return ($b->beneficio_total <=> $a->beneficio_total) ?: ((int) $b->id <=> (int) $a->id);
        });
        $topBeneficiado = array_values(array_filter($topBeneficiado, function ($item) {
            return (float) ($item->beneficio_total ?? 0) > 0;
        }));
        $topBeneficiado = array_slice($topBeneficiado, 0, 5);

        $todosSaldosZerados = true;
        foreach ($topSaldo as $item) {
            if ((float) ($item->saldo ?? 0) > 0) {
                $todosSaldosZerados = false;
                break;
            }
        }

        $this->view->addData([
            "active_menu" => "cartao-relatorio-tops",
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cartões" => ["url" => $this->router->route("admin.cartao.index"), "current" => false],
                "Ranking Geral" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Ranking Geral",
                "desc" => "Veja os cartões com maior gasto, maior saldo e maior benefício acumulado.",
            ],
        ]);

        echo $this->view->render("admin/cartao/relatorio-tops", [
            "top_gastos" => $topGastos,
            "top_saldo" => $topSaldo,
            "top_recargas" => $topRecargas,
            "top_beneficiado" => $topBeneficiado,
            "todos_saldos_zerados" => $todosSaldosZerados,
        ]);
    }

    private function formatarFrequenciaGastos(int $quantidade, ?string $primeiroGasto, ?string $ultimoGasto): string
    {
        if ($quantidade <= 1 || empty($primeiroGasto) || empty($ultimoGasto)) {
            return $quantidade === 1 ? '1 gasto no período' : '-';
        }

        $inicio = new \DateTimeImmutable(date('Y-m-d', strtotime((string) $primeiroGasto)));
        $fim = new \DateTimeImmutable(date('Y-m-d', strtotime((string) $ultimoGasto)));
        $diasBase = max(1, (int) $inicio->diff($fim)->days + 1);

        $porSemana = $quantidade / ($diasBase / 7);
        $porMes = $quantidade / ($diasBase / 30);
        $aCadaDias = $diasBase / $quantidade;

        if ($diasBase >= 60) {
            return rtrim(rtrim(number_format($porMes, 2, ',', '.'), '0'), ',') . ' gasto' . ($porMes > 1.05 ? 's' : '') . ' por mês';
        }

        if ($diasBase >= 14) {
            return rtrim(rtrim(number_format($porSemana, 2, ',', '.'), '0'), ',') . ' gasto' . ($porSemana > 1.05 ? 's' : '') . ' por semana';
        }

        return '1 gasto a cada ' . rtrim(rtrim(number_format($aCadaDias, 2, ',', '.'), '0'), ',') . ' dia' . ($aCadaDias > 1.05 ? 's' : '');
    }
}
