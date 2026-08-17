<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\DB;
use App\Core\Redirect;
use App\Core\Request;
use App\Enums\CartaoStatusEnum;
use App\Enums\MovimentacaoTipoEnum;
use App\Models\Cartao;
use App\Models\CashbackRegra;
use App\Models\Cliente;
use App\Models\Configuracao;
use App\Models\FidelidadeRegra;
use App\Models\Movimentacao;
use App\Services\CartaoService;

class CartaoController extends ControllerAdmin
{
    /**
     * Define contexto base do módulo de cartões para todas as views.
     */
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title" => "Cartões",
            "active_menu" => "cartoes",
            "page" => [
                "title" => "Cartões",
                "desc" => "Cadastre e gerencie os cartões do sistema",
            ],
            "uppers" => implode(",", Cartao::getUppers()),
            "required" => implode(",", Cartao::getRequired()),
        ]);
    }

    /**
     * Lista cartões com dados já preparados para renderização na tabela.
     */
    public function index(): void
    {
        $this->authorize("cartao_gerenciar");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cartões" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Cartões",
                "desc" => "Consulte os cartões cadastrados e seus identificadores",
            ],
        ]);

        $permissao = [
            "inserir" => $this->auth->allow("cartao_inserir"),
            "editar" => $this->auth->allow("cartao_editar"),
            "excluir" => $this->auth->allow("cartao_excluir"),
        ];

        $cartoes = Cartao::leftJoin("cliente as cl", "ct.id_cliente", "=", "cl.id")
            ->select(
                "ct.*",
                "cl.razao as cliente_razao",
                "cl.nome as cliente_nome",
                "cl.pessoa as cliente_pessoa",
                "cl.documento as cliente_documento"
            )
            ->orderBy("ct.created_at", "desc")
            ->get();

        foreach ($cartoes as $cartao) {
            $nomeCliente = trim((string) ($cartao->cliente_razao ?: $cartao->cliente_nome));
            $cartao->cliente_print     = $nomeCliente ?: '<i class="text-black-50">Sem Cliente</i>';
            $cartao->cliente_doc_print = $nomeCliente
                ? $this->formatDocumento($cartao->cliente_pessoa ?? null, $cartao->cliente_documento ?? null)
                : "";
            $cartao->codigo_print  = $this->shortValue($cartao->codigo_unico ?? "", 18);
            $cartao->status_print  = $this->badgeStatus($cartao->status ?? "");
            $cartao->saldo_print  = "R$ " . number_format((float) ($cartao->saldo ?? 0), 2, ",", ".");
            $cartao->validade_print  = !empty($cartao->validade)
                ? datebr((string) $cartao->validade) : "-";
            $cartao->ativacao_print  = !empty($cartao->data_ativacao)
                ? datebr((string) $cartao->data_ativacao) : "-";
            $cartao->bloqueio_print  = !empty($cartao->data_bloqueio)
                ? datebr((string) $cartao->data_bloqueio) : "-";
        }

        echo $this->view->render("admin/cartao/lista", [
            "dados" => $cartoes,
            "permissao" => $permissao,
        ]);
    }

    /**
     * Relatório consolidado dos bônus concedidos no período, separado entre
     * cashback e fidelidade.
     */
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

    /**
     * Relatório consolidado de recargas concedidas no período.
     */
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

    /**
     * Relatório consolidado de gastos por cartão.
     */
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

                $descricaoBaseGasto = trim((string) ($mov->descricao ?? ''));
                if ($cashbackGerado > 0) {
                    $mov->observacao_print = $descricaoBaseGasto !== ''
                        ? $descricaoBaseGasto . '. Cashback de R$ ' . number_format($cashbackGerado, 2, ',', '.') . ' lançado em linha separada.'
                        : 'Gasto registrado. Cashback de R$ ' . number_format($cashbackGerado, 2, ',', '.') . ' lançado em linha separada.';
                } elseif ($contouNaFidelidade) {
                    $mov->observacao_print = $descricaoBaseGasto !== ''
                        ? $descricaoBaseGasto . '. Sem impacto no saldo do cartão. Entrou no acumulado da fidelidade.'
                        : 'Gasto registrado sem impacto no saldo do cartão. Entrou no acumulado da fidelidade.';
                } else {
                    $mov->observacao_print = $descricaoBaseGasto !== ''
                        ? $descricaoBaseGasto . '. Sem impacto no saldo do cartão. Não entrou na contagem da fidelidade por não atingir o valor mínimo da regra.'
                        : 'Gasto registrado sem impacto no saldo do cartão. Não entrou na contagem da fidelidade por não atingir o valor mínimo da regra.';
                }

                $descricaoBaseGasto = trim((string) ($mov->descricao ?? ''));
                if ($cashbackGerado > 0) {
                    $mov->observacao_print = $descricaoBaseGasto !== ''
                        ? $descricaoBaseGasto . '. Cashback de R$ ' . number_format($cashbackGerado, 2, ',', '.') . ' lançado em linha separada.'
                        : 'Gasto registrado. Cashback de R$ ' . number_format($cashbackGerado, 2, ',', '.') . ' lançado em linha separada.';
                } elseif ($contouNaFidelidade) {
                    $mov->observacao_print = $descricaoBaseGasto !== ''
                        ? $descricaoBaseGasto . '. Sem impacto no saldo do cartão. Entrou no acumulado da fidelidade.'
                        : 'Gasto registrado sem impacto no saldo do cartão. Entrou no acumulado da fidelidade.';
                } else {
                    $mov->observacao_print = $descricaoBaseGasto !== ''
                        ? $descricaoBaseGasto . '. Sem impacto no saldo do cartão. Não entrou na contagem da fidelidade por não atingir o valor mínimo da regra.'
                        : 'Gasto registrado sem impacto no saldo do cartão. Não entrou na contagem da fidelidade por não atingir o valor mínimo da regra.';
                }

                $descricaoBaseGasto = trim((string) ($mov->descricao ?? ''));
                if ($cashbackGerado > 0) {
                    $mov->observacao_print = $descricaoBaseGasto !== ''
                        ? $descricaoBaseGasto . '. Cashback de R$ ' . number_format($cashbackGerado, 2, ',', '.') . ' lançado em linha separada.'
                        : 'Gasto registrado. Cashback de R$ ' . number_format($cashbackGerado, 2, ',', '.') . ' lançado em linha separada.';
                } elseif ($contouNaFidelidade) {
                    $mov->observacao_print = $descricaoBaseGasto !== ''
                        ? $descricaoBaseGasto . ' sem impacto no saldo do cartão. Entrou no acumulado da fidelidade.'
                        : 'Gasto registrado sem impacto no saldo do cartão. Entrou no acumulado da fidelidade.';
                } else {
                    $mov->observacao_print = $descricaoBaseGasto !== ''
                        ? $descricaoBaseGasto . ' sem impacto no saldo do cartão. Nao entrou na contagem da fidelidade por não atingir o valor mínimo da regra.'
                        : 'Gasto registrado sem impacto no saldo do cartão. Nao entrou na contagem da fidelidade por não atingir o valor mínimo da regra.';
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

    /**
     * Relatório resumido com os principais rankings operacionais dos cartões.
     */
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

    /**
     * Resolve a busca rápida do topo: se o cartão existir, abre o hub; se não,
     * redireciona para a emissão já com o código preenchido.
     */
    public function buscar(Request $request): void
    {
        $this->authorize("cartao_gerenciar");

        $data   = new Data($request->all());
        $codigo = strtoupper(trim((string) ($data->codigo ?? "")));

        if ($codigo === "") {
            $this->router->redirect("admin.cartao.index");
            return;
        }

        $service = new CartaoService();
        $cartao  = $service->buscarPorCodigo($codigo);

        if (!$cartao) {
            $isNumerico = ctype_digit($codigo);
            $params     = $isNumerico
                ? ["codigo" => $codigo]
                : ["token_nfc" => $codigo];
            $this->router->redirect("admin.cartao.novo", $params);
            return;
        }

        $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
    }

    /**
     * Hub operacional do cartão. Centraliza saldo, resumo, ações e extrato.
     */
    public function hub(Request $request): void
    {
        $this->authorize("cartao_gerenciar");

        $data   = new Data($request->all());
        $cartao = Cartao::leftJoin("cliente as cl", "ct.id_cliente", "=", "cl.id")
            ->select(
                "ct.*",
                "cl.razao as cliente_razao",
                "cl.nome as cliente_nome",
                "cl.pessoa as cliente_pessoa",
                "cl.documento as cliente_documento",
                "cl.telefone as cliente_telefone"
            )
            ->where("ct.id", "=", (int) ($data->id ?? 0))
            ->first();

        if (!$cartao) {
            $this->message->warning("Cartão não encontrado.");
            $this->router->redirect("admin.cartao.index");
            return;
        }

        $service = new CartaoService();
        $cartao = $service->sincronizarStatusPorValidade($cartao, $this->user->uid);
        $service->expirarCreditosVencidos($cartao);
        $indicadoresRecalculados = $service->reconciliarIndicadores($cartao, $this->user->uid);
        $cartao->saldo = $indicadoresRecalculados->saldo;
        $cartao->total_vendas = $indicadoresRecalculados->total_vendas;
        $cartao->total_gasto = $indicadoresRecalculados->total_gasto;
        $cartao->acumulado = $indicadoresRecalculados->acumulado;
        $cartao->valor_acumulado = $indicadoresRecalculados->valor_acumulado;
        $resumo  = $service->resumo((int) $cartao->id);

        $movimentacoes = $this->getMovimentacoesExtrato((int) $cartao->id);

        $cartao->cliente_print = trim((string) ($cartao->cliente_razao ?: $cartao->cliente_nome)) ?: "-";
        $cartao->cliente_doc_print = $this->formatDocumento(
            $cartao->cliente_pessoa ?? null,
            $cartao->cliente_documento ?? null
        );
        $cartao->cliente_phone_print = $this->formatPhone($cartao->cliente_telefone ?? null);
        $cartao->validade_print = !empty($cartao->validade)
            ? datebr((string) $cartao->validade) : "-";
        $cartao->status_print  = $this->badgeStatus($cartao->status ?? "");
        $cartao->saldo_print   = "R$ " . number_format((float) ($cartao->saldo ?? 0), 2, ",", ".");
        $idClienteHub = (int) ($cartao->id_cliente ?? 0);
        $cartao->cliente_cartoes_total = (int) Cartao::where("ct.id_cliente", "=", $idClienteHub)->count();
        $cartao->cliente_cartoes_url = $this->router->route("admin.cliente.cartoes", [
            "id" => (int) ($cartao->id_cliente ?? 0),
        ]);

        $statusEnum = CartaoStatusEnum::tryFrom((string) ($cartao->status ?? ""));

        $agora = date('Y-m-d H:i:s');

        $regraCashback = CashbackRegra::where('cr.ativo', '=', 1)
            ->whereRaw('(cr.data_inicio IS NULL OR cr.data_inicio <= ?)', [$agora])
            ->whereRaw('(cr.data_fim IS NULL OR cr.data_fim >= ?)', [$agora])
            ->orderBy('cr.id', 'desc')
            ->first();

        $regraFidelidade = FidelidadeRegra::where('fr.ativo', '=', 1)
            ->whereRaw('(fr.data_inicio IS NULL OR fr.data_inicio <= ?)', [$agora])
            ->whereRaw('(fr.data_fim IS NULL OR fr.data_fim >= ?)', [$agora])
            ->orderBy('fr.id', 'desc')
            ->first();

        $recargaMinima = (float) Configuracao::getValue('recarga_minima', 0);

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cartões" => ["url" => $this->router->route("admin.cartao.index"), "current" => false],
                "Consulta do Cartão" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Consulta do Cartão",
                "desc"  => "Resumo, saldo e operações disponíveis",
            ],
        ]);

        echo $this->view->render("admin/cartao/hub", [
            "cartao"           => $cartao,
            "resumo"           => $resumo,
            "movimentacoes"    => $movimentacoes,
            "movimentacao_tipos" => $this->getExtratoTiposFiltro(),
            "status_enum"      => $statusEnum,
            "regra_cashback"   => $regraCashback,
            "regra_fidelidade" => $regraFidelidade,
            "recarga_minima"   => $recargaMinima,
            "csrf"             => $this->csrf->generate(),
            "extrato_filtro_url" => $this->router->route("admin.cartao.extrato.filtro", ["id" => $cartao->id]),
            "permissao"        => [
                "inserir" => $this->auth->allow("cartao_inserir"),
                "editar"  => $this->auth->allow("cartao_editar"),
                "mov"     => $this->auth->allow("movimentacao_inserir"),
            ],
        ]);
    }

    /**
     * Endpoint legado do filtro de extrato. Hoje o hub usa filtro local em
     * tela, mas mantemos a rota para reaproveitamento futuro e compatibilidade.
     */
    public function extratoFiltro(Request $request): void
    {
        $this->authorize("cartao_gerenciar");

        $data = new Data($request->all());
        $idCartao = (int) ($data->id ?? 0);
        $cartao = Cartao::find($idCartao);

        header("Content-Type: application/json; charset=utf-8");

        if (!$cartao) {
            echo json_encode([
                "success" => false,
                "message" => "Cartão não encontrado.",
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $filtros = [
            "tipo" => trim((string) ($data->tipo ?? "")),
            "data_inicial" => trim((string) ($data->data_inicial ?? "")),
            "data_final" => trim((string) ($data->data_final ?? "")),
        ];

        $movimentacoes = $this->getMovimentacoesExtrato($idCartao, $filtros);
        $html = $this->view->render("admin/cartao/partials/extrato-tabela", [
            "movimentacoes" => $movimentacoes,
        ]);

        echo json_encode([
            "success" => true,
            "html" => $html,
            "total" => count($movimentacoes),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Exibe o formulário de emissão do cartão.
     */
    public function new(Request $request): void
    {
        $this->authorize("cartao_inserir");

        $data            = new Data($request->all());
        $codigoPrefill   = strtoupper(trim((string) ($data->codigo ?? "")));
        $tokenNfcPrefill = trim((string) ($data->token_nfc ?? ""));
        $clientes        = $this->getClientesForForm();
        $clienteObrigatorio = Configuracao::getValue("cartao_cliente_obrigatorio", "1") === "1";

        $requiredFields = Cartao::getRequired();
        if ($clienteObrigatorio) {
            array_unshift($requiredFields, "id_cliente");
        }

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cartões" => ["url" => $this->router->route("admin.cartao.index"), "current" => false],
                "Adicionar Cartão" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Adicionar Cartão",
                "desc" => "Selecione o cliente e informe os dados do cartão",
            ],
            "required" => implode(",", $requiredFields),
        ]);

        echo $this->view->render("admin/cartao/novo", [
            "csrf"                 => $this->csrf->generate(),
            "clientes"             => $clientes,
            "url_back"             => $this->router->route("admin.cartao.index"),
            "status_padrao"        => CartaoStatusEnum::ATIVO->value,
            "form_action"          => $this->router->route("admin.cartao.insert"),
            "form_title"           => "Emissão do cartão",
            "form_subtitle"        => "O cartão será cadastrado já como ativo e vinculado a um cliente.",
            "cartao"               => null,
            "codigo_prefill"       => $codigoPrefill,
            "token_nfc_prefill"    => $tokenNfcPrefill,
            "clienteIsObrigatorio" => $clienteObrigatorio,
        ]);
    }

    /**
     * Exibe a edição do cartão existente.
     */
    public function edit(Request $request): void
    {
        $this->authorize("cartao_editar");

        $data = new Data($request->all());
        $cartao = Cartao::find((int) ($data->id ?? 0));

        if (!$cartao) {
            $this->message->warning("Cartão não encontrado.");
            $this->router->redirect("admin.cartao.index");
            return;
        }

        $clientes           = $this->getClientesForForm();
        $clienteObrigatorio = Configuracao::getValue("cartao_cliente_obrigatorio", "1") === "1";

        $requiredFields = Cartao::getRequired();
        if ($clienteObrigatorio) {
            array_unshift($requiredFields, "id_cliente");
        }

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cartões" => ["url" => $this->router->route("admin.cartao.index"), "current" => false],
                "Editar Cartão" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Editar Cartão",
                "desc" => "Atualize os dados cadastrais do cartão.",
            ],
            "required" => implode(",", $requiredFields),
        ]);

        echo $this->view->render("admin/cartao/novo", [
            "csrf"          => $this->csrf->generate(),
            "clientes"      => $clientes,
            "url_back"             => $this->router->route("admin.cartao.index"),
            "status_padrao"        => $cartao->status ?? CartaoStatusEnum::ATIVO->value,
            "form_action"          => $this->router->route("admin.cartao.update"),
            "form_title"           => "Edição do cartão",
            "form_subtitle"        => "Ajuste o vínculo com cliente, validade e observações do cartão.",
            "cartao"               => $cartao,
            "codigo_prefill"       => "",
            "clienteIsObrigatorio" => $clienteObrigatorio,
        ]);
    }

    /**
     * Emite um novo cartão validando cliente e unicidade do código.
     */
    public function create(Request $request): void
    {
        $this->authorize("cartao_inserir");

        $data = new Data($request->all());

        $data->nullIfEmpty(["id_cliente", "validade"]);

        $clienteObrigatorio = Configuracao::getValue("cartao_cliente_obrigatorio", "1") === "1";
        $idCliente = (int) ($data->id_cliente ?? 0);

        if ($clienteObrigatorio) {
            if ($idCliente <= 0 || !Cliente::find($idCliente)) {
                $this->message->warning("Selecione um cliente válido");
                Redirect::referer();
                return;
            }
        } elseif ($idCliente > 0 && !Cliente::find($idCliente)) {
            $this->message->warning("Cliente inválido");
            Redirect::referer();
            return;
        }

        $codigo = $this->normalizeCodigoUnico((string) ($data->codigo_unico ?? ""));
        if ($codigo === "") {
            $this->message->warning("Informe o código único do cartão.");
            Redirect::referer();
            return;
        }

        if ($codigo === "000000") {
            $this->message->warning("Informe um código maior que zero.");
            Redirect::referer();
            return;
        }

        if ($this->codigoJaExiste($codigo)) {
            $this->message->warning("Este código já está em uso.");
            Redirect::referer();
            return;
        }

        $tokenNfc = strtoupper(trim((string) ($data->token_nfc ?? "")));

        $payload = [
            "id_cliente"   => $idCliente > 0 ? $idCliente : null,
            "codigo_unico" => $codigo,
            "token_nfc"    => $tokenNfc !== "" ? $tokenNfc : null,
            "validade"     => $data->validade,
            "observacoes"  => trim((string) ($data->observacoes ?? "")),
            "data_emissao" => date("Y-m-d H:i:s"),
            "data_ativacao" => date("Y-m-d H:i:s"),
            "status"       => CartaoStatusEnum::ATIVO->value,
            "created_by"   => $this->user->uid,
        ];

        $result = Cartao::create($payload);

        $this->message->success("Cartão cadastrado com sucesso");
        $this->router->redirect("admin.cartao.hub", [
            "id" => $result->id,
        ]);
    }

    /**
     * Atualiza os dados cadastrais do cartão.
     */
    public function update(Request $request): void
    {
        $this->authorize("cartao_editar");

        $data = new Data($request->all());
        $idCartao = (int) ($data->id ?? 0);

        $data->nullIfEmpty(["id_cliente", "validade"]);

        // ENCONTRAR DADOS DO CARTÃO
        $cartao = Cartao::find($idCartao);

        if (!$cartao) {
            $this->message->warning("Cartão não encontrado.");
            Redirect::referer();
            return;
        }

        $clienteObrigatorio = Configuracao::getValue("cartao_cliente_obrigatorio", "1") === "1";
        $idCliente = (int) ($data->id_cliente ?? 0);

        if ($clienteObrigatorio) {
            if ($idCliente <= 0 || !Cliente::find($idCliente)) {
                $this->message->warning("Selecione um cliente válido");
                Redirect::referer();
                return;
            }
        } elseif ($idCliente > 0 && !Cliente::find($idCliente)) {
            $this->message->warning("Cliente inválido");
            Redirect::referer();
            return;
        }

        $codigo = $this->normalizeCodigoUnico((string) ($data->codigo_unico ?? ""));
        if ($codigo === "") {
            $this->message->warning("Informe o código único do cartão.");
            Redirect::referer();
            return;
        }

        if ($codigo === "000000") {
            $this->message->warning("Informe um código maior que zero.");
            Redirect::referer();
            return;
        }

        if ($this->codigoJaExiste($codigo, $idCartao)) {
            $this->message->warning("Este código já está em uso.");
            Redirect::referer();
            return;
        }

        $statusAtual = strtoupper(trim((string) ($cartao->status ?? CartaoStatusEnum::ATIVO->value)));
        $statusSelecionado = strtoupper(trim((string) ($data->status ?? $statusAtual)));
        $statusEnum = CartaoStatusEnum::tryFrom($statusSelecionado);

        if (!$statusEnum) {
            $this->message->warning("Selecione um status válido.");
            Redirect::referer();
            return;
        }

        $tokenNfc = strtoupper(trim((string) ($data->token_nfc ?? "")));

        Cartao::updateBy($idCartao, [
            "id_cliente"   => $idCliente > 0 ? $idCliente : null,
            "codigo_unico" => $codigo,
            "token_nfc"    => $tokenNfc !== "" ? $tokenNfc : null,
            "validade"     => $data->validade,
            "observacoes"  => trim((string) ($data->observacoes ?? "")),
            "status"       => $statusEnum->value,
            "updated_by" => $this->user->uid,
        ]);

        $this->message->success("Cartão atualizado com sucesso");
        $this->router->redirect("admin.cartao.hub", ["id" => $idCartao]);
    }

    /**
     * Endpoint usado pelo validate remote para verificar unicidade do token NFC.
     */
    public function tokenNfcDisponivel(Request $request): void
    {
        $data      = new Data($request->all());
        $token     = trim((string) ($data->token_nfc ?? ""));
        $ignoreId  = (int) ($data->id ?? 0);

        header("Content-Type: text/plain; charset=utf-8");

        if ($token === "") {
            echo "true";
            return;
        }

        $query = Cartao::where("ct.token_nfc", "=", $token);
        if ($ignoreId > 0) {
            $query->where("ct.id", "!=", $ignoreId);
        }

        echo $query->count() > 0 ? "false" : "true";
    }

    /**
     * Endpoint usado pelo validate remote para verificar unicidade do código.
     */
    public function codigoDisponivel(Request $request): void
    {
        $data = new Data($request->all());
        $codigo = $this->normalizeCodigoUnico((string) ($data->codigo_unico ?? $data->codigo ?? ""));
        $ignoreId = (int) ($data->id ?? 0);

        header("Content-Type: text/plain; charset=utf-8");

        if ($codigo === "") {
            echo "false";
            return;
        }

        if ($codigo === "000000") {
            echo "false";
            return;
        }

        echo $this->codigoJaExiste($codigo, $ignoreId > 0 ? $ignoreId : null) ? "false" : "true";
    }

    /**
     * Processa uma recarga administrativa.
     */
    public function recarregar(Request $request): void
    {
        $this->authorize("movimentacao_inserir");

        $data   = new Data($request->all());
        $cartao = Cartao::find((int) ($data->id ?? 0));

        if (!$cartao) {
            $this->message->warning("Cartão não encontrado.");
            $this->router->redirect("admin.cartao.index");
            return;
        }

        $valor = $this->parseValor((string) ($data->valor ?? ''));
        $recargaMinima = (float) Configuracao::getValue('recarga_minima', 0);

        if ($valor <= 0) {
            $this->message->warning("Informe um valor de recarga válido.");
            $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
            return;
        }

        if ($recargaMinima > 0 && $valor < $recargaMinima) {
            $this->message->warning(
                "A recarga minima permitida e de R$ " . number_format($recargaMinima, 2, ',', '.') . "."
            );
            $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
            return;
        }

        try {
            $service = new CartaoService();
            $service->recarregar(
                cartao:         $cartao,
                valor:          $valor,
                idOperador:     $this->user->uid,
                origem:         'admin',
                formaPagamento: trim((string) ($data->forma_pagamento ?? '')) ?: null,
                descricao:      trim((string) ($data->descricao ?? '')) ?: null,
                createdBy:      $this->user->uid
            );

            $this->message->success(
                "Recarga de R$ " . number_format($valor, 2, ',', '.') . " realizada com sucesso."
            );
        } catch (\RuntimeException $e) {
            $this->message->warning($e->getMessage());
        }

        $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
    }

    /**
     * Registra um gasto no cartão, avaliando automaticamente cashback e
     * fidelidade quando houver regras vigentes.
     */
    public function lancarVenda(Request $request): void
    {
        $this->authorize("movimentacao_inserir");

        $data   = new Data($request->all());
        $cartao = Cartao::find((int) ($data->id ?? 0));

        if (!$cartao) {
            $this->message->warning("Cartão não encontrado.");
            $this->router->redirect("admin.cartao.index");
            return;
        }

        $valor = $this->parseValor((string) ($data->valor ?? ''));

        if ($valor <= 0) {
            $this->message->warning("Informe o valor do gasto.");
            $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
            return;
        }

        try {
            $service    = new CartaoService();
            $resultado  = $service->lancarVendaOperacional(
                cartao:    $cartao,
                valorVenda: $valor,
                idOperador: $this->user->uid,
                descricao:  trim((string) ($data->descricao ?? '')) ?: null,
                createdBy:  $this->user->uid
            );

            $this->message->success(
                "Gasto de R$ " . number_format($valor, 2, ',', '.') . " registrado."
            );

            if ($resultado->cashback_aplicado > 0) {
                $this->message->success(
                    "Cashback de R$ " . number_format($resultado->cashback_aplicado, 2, ',', '.') . " creditado."
                );
            }

            if ($resultado->fidelidade_aplicada) {
                $bonus = number_format($resultado->fidelidade_valor, 2, ',', '.');
                $quantidadeBonus = (int) ($resultado->fidelidade_quantidade ?? 1);
                if ($quantidadeBonus > 1) {
                    $this->message->success("Bônus fidelidade: {$quantidadeBonus} creditos somando R$ {$bonus}.");
                } else {
                    $this->message->success("Bônus fidelidade de R$ {$bonus} creditado.");
                }
            }


        } catch (\RuntimeException $e) {
            $this->message->warning($e->getMessage());
        }

        $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
    }

    /**
     * Registra um débito simples no saldo do cartão.
     */
    public function consumo(Request $request): void
    {
        $this->authorize("movimentacao_inserir");

        $data   = new Data($request->all());
        $cartao = Cartao::find((int) ($data->id ?? 0));

        if (!$cartao) {
            $this->message->warning("Cartão não encontrado.");
            $this->router->redirect("admin.cartao.index");
            return;
        }

        $valor = $this->parseValor((string) ($data->valor ?? ''));

        if ($valor <= 0) {
            $this->message->warning("Informe um valor de consumo válido.");
            $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
            return;
        }

        try {
            $service = new CartaoService();
            $service->registrarConsumo(
                cartao:    $cartao,
                valor:     $valor,
                idOperador: $this->user->uid,
                origem:    'admin',
                descricao: trim((string) ($data->descricao ?? '')) ?: null,
                createdBy: $this->user->uid
            );

            $this->message->success(
                "Consumo de R$ " . number_format($valor, 2, ',', '.') . " debitado do cartão."
            );
        } catch (\RuntimeException $e) {
            $this->message->warning($e->getMessage());
        }

        $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
    }

    /**
     * Ajuste manual administrativo, exposto no hub como crédito ou débito.
     */
    public function ajuste(Request $request): void
    {
        $this->authorize("movimentacao_inserir");

        $data   = new Data($request->all());
        $cartao = Cartao::find((int) ($data->id ?? 0));

        if (!$cartao) {
            $this->message->warning("Cartão não encontrado.");
            $this->router->redirect("admin.cartao.index");
            return;
        }

        $valor = $this->parseValor((string) ($data->valor ?? ''));
        $sentido = strtoupper(trim((string) ($data->sentido ?? '')));
        $descricao = trim((string) ($data->descricao ?? ''));

        if ($valor <= 0) {
            $this->message->warning("Informe um valor de ajuste válido.");
            $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
            return;
        }

        try {
            $service = new CartaoService();
            $service->ajustar(
                cartao: $cartao,
                valor: $valor,
                sentido: $sentido,
                idOperador: $this->user->uid,
                descricao: $descricao,
                createdBy: $this->user->uid
            );

            $acao = $sentido === 'DEBITO' ? 'débito' : 'crédito';
            $this->message->success(
                "Ajuste de {$acao} de R$ " . number_format($valor, 2, ',', '.') . " realizado com sucesso."
            );
        } catch (\RuntimeException $e) {
            $this->message->warning($e->getMessage());
        }

        $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
    }

    /**
     * Estorna uma movimentação do próprio cartão, com trava contra duplicidade
     * e contra estorno de estorno.
     */
    public function estornar(Request $request): void
    {
        $this->authorize("movimentacao_inserir");

        $data   = new Data($request->all());
        $cartao = Cartao::find((int) ($data->id ?? 0));

        if (!$cartao) {
            $this->message->warning("Cartão não encontrado.");
            $this->router->redirect("admin.cartao.index");
            return;
        }

        $movOrigem = Movimentacao::where('mv.id', '=', (int) ($data->id_movimentacao ?? 0))
            ->where('mv.id_cartao', '=', (int) $cartao->id)
            ->first();

        if (!$movOrigem) {
            $this->message->warning("Movimentação não encontrada.");
            $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
            return;
        }

        if ((string) ($movOrigem->tipo ?? '') === MovimentacaoTipoEnum::ESTORNO->value) {
            $this->message->warning("Não é possível estornar um estorno.");
            $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
            return;
        }

        if ($this->movimentacaoJaEstornada((int) $movOrigem->id)) {
            $this->message->warning("Esta movimentação já foi estornada.");
            $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
            return;
        }

        try {
            $service = new CartaoService();
            $service->estornar(
                cartao: $cartao,
                movOrigem: $movOrigem,
                idOperador: $this->user->uid,
                descricao: trim((string) ($data->descricao ?? '')),
                createdBy: $this->user->uid
            );

            $this->message->success("Estorno realizado com sucesso.");
        } catch (\RuntimeException $e) {
            $this->message->warning($e->getMessage());
        }

        $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
    }

    /**
     * Tela intermediária de ações após emissão. Mantida por compatibilidade,
     * embora o fluxo principal hoje siga direto para o hub.
     */
    public function actions(Request $request): void
    {
        $this->authorize("cartao_inserir");

        $data = new Data($request->all());
        $cartao = Cartao::leftJoin("cliente as cl", "ct.id_cliente", "=", "cl.id")
            ->select(
                "ct.*",
                "cl.razao as cliente_razao",
                "cl.nome as cliente_nome",
                "cl.pessoa as cliente_pessoa",
                "cl.documento as cliente_documento",
                "cl.telefone as cliente_telefone"
            )
            ->where("ct.id", "=", (int) ($data->id ?? 0))
            ->first();

        if (!$cartao) {
            $this->message->warning("Cartão não encontrado.");
            $this->router->redirect("admin.cartao.index");
            return;
        }

        $cartao->cliente_print = trim((string) ($cartao->cliente_razao ?: $cartao->cliente_nome)) ?: "-";
        $cartao->cliente_doc_print = $this->formatDocumento(
            $cartao->cliente_pessoa ?? null,
            $cartao->cliente_documento ?? null
        );
        $cartao->cliente_phone_print = $this->formatPhone($cartao->cliente_telefone ?? null);
        $cartao->codigo_print = $this->shortValue($cartao->codigo_unico ?? "", 18);
        $cartao->validade_print = !empty($cartao->validade) ? datebr((string) $cartao->validade) : "-";

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cartões" => ["url" => $this->router->route("admin.cartao.index"), "current" => false],
                "Ações do Cartão" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Ações do Cartão",
                "desc" => "Escolha a próxima operação para o cartão recém-cadastrado",
            ],
        ]);

        echo $this->view->render("admin/cartao/acoes", [
            "cartao" => $cartao,
            "cashback_disponivel" => false,
            "url_back" => $this->router->route("admin.cartao.index"),
        ]);
    }

    /**
     * Aplica manualmente os ciclos de fidelidade já atingidos pelo cartão.
     */
    public function aplicarFidelidade(Request $request): void
    {
        $this->authorize("cartao_editar");

        $data   = new Data($request->all());
        $cartao = Cartao::find((int) ($data->id ?? 0));

        if (!$cartao) {
            $this->message->warning("Cartão não encontrado.");
            $this->router->redirect("admin.cartao.index");
            return;
        }

        $agora = date('Y-m-d H:i:s');
        $regra = \App\Models\FidelidadeRegra::where('fr.ativo', '=', 1)
            ->whereRaw('(fr.data_inicio IS NULL OR fr.data_inicio <= ?)', [$agora])
            ->whereRaw('(fr.data_fim IS NULL OR fr.data_fim >= ?)', [$agora])
            ->orderBy('fr.id', 'desc')
            ->first();

        if (!$regra) {
            $this->message->warning("Nenhuma regra de fidelidade vigente.");
            $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
            return;
        }

        $gatilho    = (int) ($regra->quantidade_vendas ?? 0);
        $valorBonus = (float) ($regra->valor_saldo ?? 0);
        $acumulado  = (int) ($cartao->acumulado ?? 0);

        if ($valorBonus > 0 && $this->cartaoElegivelParaRegraFidelidade($cartao, $regra)) {
            $service = new CartaoService();
            $aplicados = 0;

            while (true) {
                $cartaoFresh = Cartao::find((int) $cartao->id);

                if (!$cartaoFresh || !$this->cartaoElegivelParaRegraFidelidade($cartaoFresh, $regra)) {
                    break;
                }

                try {
                    $gatilhoUsado = $this->resolverGatilhoFidelidadeAtingido($cartaoFresh, $regra);
                    $desc = $this->descricaoBonusFidelidade($regra, $gatilhoUsado, $aplicados + 1);

                    $service->aplicarFidelidade($cartaoFresh, $valorBonus, $this->user->uid, $desc);
                    $this->consumirGatilhoFidelidadeCartao($cartaoFresh, $regra, $gatilhoUsado);
                    $aplicados++;
                } catch (\RuntimeException) {
                    break;
                }
            }

            if ($aplicados > 0) {
                $bonus = number_format($valorBonus * $aplicados, 2, ',', '.');
                $this->message->success("Bônus aplicado: {$aplicados} crédito(s) de R$ {$bonus} no cartão.");
                $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
                return;
            }
        }

        $gatilho = 0;
        $acumulado = 0;

        if ($gatilho <= 0 || $valorBonus <= 0 || $acumulado < $gatilho) {
            $this->message->warning("Cartão não elegível para bônus no momento.");
            $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
            return;
        }

        $ciclos  = (int) floor($acumulado / $gatilho);
        $resto   = $acumulado % $gatilho;
        $service = new CartaoService();
        $aplicados = 0;

        $sufixo = $gatilho > 1 ? 's' : '';
        for ($i = 0; $i < $ciclos; $i++) {
            try {
                $cartaoFresh = Cartao::find((int) $cartao->id);
                if (!$cartaoFresh) {
                    break;
                }
                $desc = "Bônus manual a cada {$gatilho} gasto{$sufixo} (ciclo " . ($i + 1) . "/{$ciclos})";
                $service->aplicarFidelidade($cartaoFresh, $valorBonus, $this->user->uid, $desc);
                $aplicados++;
            } catch (\RuntimeException) {
                break;
            }
        }

        Cartao::updateBy((int) $cartao->id, ['acumulado' => $resto]);

        $bonus = number_format($valorBonus * $aplicados, 2, ',', '.');
        $this->message->success("Bônus aplicado: {$aplicados} crédito(s) de R$ {$bonus} no cartão.");
        $this->router->redirect("admin.cartao.hub", ["id" => $cartao->id]);
    }

    /**
     * Exporta o extrato do cartão em XLS.
     */
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

    private function cartaoElegivelParaRegraFidelidade(object $cartao, object $regra): bool
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

    private function resolverGatilhoFidelidadeAtingido(object $cartao, object $regra): string
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

    private function consumirGatilhoFidelidadeCartao(object $cartao, object $regra, string $gatilhoUsado): void
    {
        if ($gatilhoUsado === 'valor') {
            $gatilhoValor = round((float) ($regra->valor_acumulado_minimo ?? 0), 2);
            $valorAtual = round((float) ($cartao->valor_acumulado ?? 0), 2);

            $updates = [];
            if ($this->gatilhoValorEhUnico($regra)) {
                $updates["fidelidade_valor_unico_regra_id"] = (int) ($regra->id ?? 0) ?: null;
            } else {
                $updates["valor_acumulado"] = max(0, round($valorAtual - $gatilhoValor, 2));
            }

            Cartao::updateBy((int) $cartao->id, $updates);
            return;
        }

        $gatilhoVendas = (int) ($regra->quantidade_vendas ?? 0);
        $acumuladoAtual = (int) ($cartao->acumulado ?? 0);

        Cartao::updateBy((int) $cartao->id, [
            "acumulado" => max(0, $acumuladoAtual - $gatilhoVendas),
        ]);
    }

    private function descricaoBonusFidelidade(object $regra, string $gatilhoUsado, int $ciclo): string
    {
        if ($gatilhoUsado === 'valor') {
            $gatilhoValor = round((float) ($regra->valor_acumulado_minimo ?? 0), 2);

            return "Bônus manual por fidelidade a cada R$ "
                . number_format($gatilhoValor, 2, ',', '.')
                . " acumulados (ciclo {$ciclo})";
        }

        $gatilhoVendas = (int) ($regra->quantidade_vendas ?? 0);
        $valorMinimoVenda = round((float) ($regra->valor_minimo_venda ?? 0), 2);

        $descricao = "Bônus manual por fidelidade a cada {$gatilhoVendas} gasto"
            . ($gatilhoVendas > 1 ? 's' : '')
            . " (ciclo {$ciclo})";

        if ($valorMinimoVenda > 0) {
            $descricao .= " com valor mínimo de R$ " . number_format($valorMinimoVenda, 2, ',', '.');
        }

        return $descricao;
    }

    public function extratoXls(Request $request): void
    {
        $this->authorize("cartao_gerenciar");

        $data   = new Data($request->all());
        $cartao = Cartao::find((int) ($data->id ?? 0));

        if (!$cartao) {
            $this->message->warning("Cartão não encontrado.");
            $this->router->redirect("admin.cartao.index");
            return;
        }

        $movimentacoes = $this->getMovimentacoesExtrato((int) $cartao->id);

        $excel = new \App\Lib\Excel();
        $excel->setTitle('Extrato');

        $excel->addLine(['Data', 'Tipo', 'Sentido', 'Valor (R$)', 'Saldo após (R$)', 'Forma', 'Observação']);

        foreach ($movimentacoes as $mov) {
            $tipoLabel = trim(strip_tags((string) ($mov->tipo_print ?? '')));
            $sentidoLabel = $mov->is_credito === null
                ? '-'
                : ((string) ($mov->sentido ?? '-'));

            $excel->addLine([
                !empty($mov->created_at) ? datetimebr((string) $mov->created_at) : '-',
                $tipoLabel !== '' ? $tipoLabel : (string) ($mov->tipo ?? '-'),
                $sentidoLabel,
                $mov->is_credito === null ? null : (float) ($mov->valor ?? 0),
                (float) ($mov->saldo_posterior ?? 0),
                $this->formatFormaPagamento((string) ($mov->forma_pagamento ?? '')),
                (string) ($mov->observacao_print ?? $mov->descricao ?? ''),
            ]);
        }

        $excel->setHeader(1);
        $excel->setAutoWidthColumn(7);
        $excel->setFormatMoney('D2:E' . (count($movimentacoes) + 1));

        $nome = 'extrato-' . ($cartao->codigo_unico ?? $cartao->id) . '.xlsx';
        $excel->create($nome);
    }

    /**
     * Exporta o extrato do cartão em PDF.
     */
    public function extratoPdf(Request $request): void
    {
        $this->authorize("cartao_gerenciar");

        $data   = new Data($request->all());
        $cartao = Cartao::leftJoin("cliente as cl", "ct.id_cliente", "=", "cl.id")
            ->select("ct.*", "cl.razao as cliente_razao", "cl.nome as cliente_nome")
            ->where("ct.id", "=", (int) ($data->id ?? 0))
            ->first();

        if (!$cartao) {
            $this->message->warning("Cartão não encontrado.");
            $this->router->redirect("admin.cartao.index");
            return;
        }

        $movimentacoes = $this->getMovimentacoesExtrato((int) $cartao->id);

        $cliente = trim((string) ($cartao->cliente_razao ?: $cartao->cliente_nome)) ?: '-';
        $codigo  = htmlspecialchars((string) ($cartao->codigo_unico ?? '-'), ENT_QUOTES, 'UTF-8');
        $saldo   = 'R$ ' . number_format((float) ($cartao->saldo ?? 0), 2, ',', '.');

        $linhas = '';
        foreach ($movimentacoes as $mov) {
            $data_print  = !empty($mov->created_at) ? datetimebr((string) $mov->created_at) : '-';
            if ($mov->is_credito === null) {
                $cor = '#6c757d';
                $valor_print = '-';
            } else {
                $sentido  = (string) ($mov->sentido ?? '');
                $cor      = $sentido === 'CREDITO' ? '#198754' : '#dc3545';
                $sinal    = $sentido === 'CREDITO' ? '+' : '-';
                $valor_print = $sinal . ' R$ ' . number_format((float) ($mov->valor ?? 0), 2, ',', '.');
            }
            $saldo_print = 'R$ ' . number_format((float) ($mov->saldo_posterior ?? 0), 2, ',', '.');
            $forma_print = htmlspecialchars(
                $this->formatFormaPagamento((string) ($mov->forma_pagamento ?? '')),
                ENT_QUOTES,
                'UTF-8'
            );
            $obs_print   = htmlspecialchars((string) ($mov->observacao_print ?? $mov->descricao ?? ''), ENT_QUOTES, 'UTF-8');
            $tipo_print  = htmlspecialchars(trim(strip_tags((string) ($mov->tipo_print ?? ''))), ENT_QUOTES, 'UTF-8');

            $linhas .= '<tr>'
                . '<td>' . $data_print . '</td>'
                . '<td>' . $tipo_print . '</td>'
                . '<td style="color:' . $cor . ';text-align:right">' . $valor_print . '</td>'
                . '<td style="text-align:right">' . $saldo_print . '</td>'
                . '<td>' . $forma_print . '</td>'
                . '<td>' . $obs_print . '</td>'
                . '</tr>';
        }

        $html = '
        <style>
            body { font-family: Arial, sans-serif; font-size: 11px; }
            h2 { font-size: 14px; margin-bottom: 4px; }
            .meta { color: #555; margin-bottom: 12px; font-size: 10px; }
            table { width: 100%; border-collapse: collapse; }
            th { background: #343a40; color: #fff; padding: 5px 6px; text-align: left; font-size: 10px; }
            td { padding: 4px 6px; border-bottom: 1px solid #e0e0e0; font-size: 10px; }
            tr:nth-child(even) td { background: #f8f9fa; }
        </style>
        <h2>Extrato do Cart&atilde;o</h2>
        <div class="meta">
            Cart&atilde;o: <strong>' . $codigo . '</strong> &nbsp;|&nbsp;
            Cliente: <strong>' . htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8') . '</strong> &nbsp;|&nbsp;
            Saldo atual: <strong>' . $saldo . '</strong> &nbsp;|&nbsp;
            Gerado em: ' . datetimebr(date('Y-m-d H:i:s')) . '
        </div>
        <table>
            <thead>
                <tr>
                    <th>Data</th><th>Tipo</th><th style="text-align:right">Valor</th>
                    <th style="text-align:right">Saldo ap&oacute;s</th><th>Forma</th><th>Observa&ccedil;&atilde;o</th>
                </tr>
            </thead>
            <tbody>' . $linhas . '</tbody>
        </table>';

        $mpdf = new \Mpdf\Mpdf([
            'orientation'   => 'L',
            'margin_left'   => 10,
            'margin_right'  => 10,
            'margin_top'    => 10,
            'margin_bottom' => 10,
        ]);
        $mpdf->SetTitle('Extrato - ' . ($cartao->codigo_unico ?? ''));
        $mpdf->WriteHTML($html);
        $mpdf->Output('extrato-' . ($cartao->codigo_unico ?? $cartao->id) . '.pdf', 'I');
        exit;
    }

    /**
     * Monta a lista de clientes com label operacional pronto para os selects.
     */
    private function getClientesForForm(): array
    {
        $clientes = Cliente::select("c.id", "c.razao", "c.nome", "c.documento", "c.pessoa", "c.telefone")
            ->orderBy("c.razao")
            ->get();

        foreach ($clientes as $cliente) {
            $cliente->label_print = $this->clienteLabel(
                $cliente->razao ?? null,
                $cliente->nome ?? null,
                $cliente->pessoa ?? null,
                $cliente->documento ?? null,
                $cliente->telefone ?? null
            );
        }

        return $clientes;
    }

    /**
     * Busca o extrato do cartão aplicando filtros opcionais de tipo e período.
     */
    private function getMovimentacoesExtrato(int $idCartao, array $filtros = []): array
    {
        $query = Movimentacao::where('mv.id_cartao', '=', $idCartao);

        $tipo = trim((string) ($filtros["tipo"] ?? ""));
        if ($tipo !== "") {
            $query->where('mv.tipo', '=', $tipo);
        }

        $dataInicial = trim((string) ($filtros["data_inicial"] ?? ""));
        if ($dataInicial !== "") {
            $query->whereRaw('DATE(mv.created_at) >= ?', [$dataInicial]);
        }

        $dataFinal = trim((string) ($filtros["data_final"] ?? ""));
        if ($dataFinal !== "") {
            $query->whereRaw('DATE(mv.created_at) <= ?', [$dataFinal]);
        }

        $movimentacoes = $query
            ->orderBy('mv.created_at', 'desc')
            ->orderBy('mv.id', 'desc')
            ->get();

        return $this->decorateMovimentacoesExtrato($movimentacoes);
    }

    /**
     * Enriquece as movimentações com badges, ícones e flags usadas no extrato.
     */
    private function decorateMovimentacoesExtrato(array $movimentacoes): array
    {
        usort($movimentacoes, function ($a, $b) {
            $aRefVenda = (string) ($a->referencia_tipo ?? '') === 'movimentacao' ? (int) ($a->id_referencia ?? 0) : 0;
            $bRefVenda = (string) ($b->referencia_tipo ?? '') === 'movimentacao' ? (int) ($b->id_referencia ?? 0) : 0;
            $aId = (int) ($a->id ?? 0);
            $bId = (int) ($b->id ?? 0);

            if ($aRefVenda > 0 && $aRefVenda === $bId) {
                return 1;
            }

            if ($bRefVenda > 0 && $bRefVenda === $aId) {
                return -1;
            }

            $dataA = (string) ($a->created_at ?? '');
            $dataB = (string) ($b->created_at ?? '');

            if ($dataA !== $dataB) {
                return strcmp($dataB, $dataA);
            }

            $aIsVenda = (string) ($a->tipo ?? '') === MovimentacaoTipoEnum::DEBITO->value
                && (string) ($a->referencia_tipo ?? '') === 'venda';
            $bIsVenda = (string) ($b->tipo ?? '') === MovimentacaoTipoEnum::DEBITO->value
                && (string) ($b->referencia_tipo ?? '') === 'venda';

            if ($aIsVenda !== $bIsVenda) {
                return $aIsVenda ? -1 : 1;
            }

            $aIsBonus = in_array((string) ($a->tipo ?? ''), [
                MovimentacaoTipoEnum::CASHBACK->value,
                MovimentacaoTipoEnum::FIDELIDADE->value,
            ], true);
            $bIsBonus = in_array((string) ($b->tipo ?? ''), [
                MovimentacaoTipoEnum::CASHBACK->value,
                MovimentacaoTipoEnum::FIDELIDADE->value,
            ], true);

            if ($aIsBonus !== $bIsBonus) {
                return $aIsBonus ? 1 : -1;
            }

            return ((int) ($b->id ?? 0)) <=> ((int) ($a->id ?? 0));
        });

        $movIds = array_map(static fn ($mov) => (int) ($mov->id ?? 0), $movimentacoes);
        $estornadas = [];
        $cashbacksPorVenda = [];
        $regraFidelidadeAtual = FidelidadeRegra::where('fr.ativo', '=', 1)
            ->whereRaw('(fr.data_inicio IS NULL OR fr.data_inicio <= ?)', [date('Y-m-d H:i:s')])
            ->whereRaw('(fr.data_fim IS NULL OR fr.data_fim >= ?)', [date('Y-m-d H:i:s')])
            ->orderBy('fr.id', 'desc')
            ->first();

        if (!empty($movIds)) {
            $estornos = Movimentacao::where('mv.referencia_tipo', '=', 'movimentacao')
                ->where('mv.tipo', '=', MovimentacaoTipoEnum::ESTORNO->value)
                ->whereIn('mv.id_referencia', $movIds)
                ->get();

            foreach ($estornos as $estorno) {
                $estornadas[(int) ($estorno->id_referencia ?? 0)] = true;
            }

            $cashbacks = Movimentacao::where('mv.referencia_tipo', '=', 'movimentacao')
                ->where('mv.tipo', '=', MovimentacaoTipoEnum::CASHBACK->value)
                ->whereIn('mv.id_referencia', $movIds)
                ->get();

            foreach ($cashbacks as $cashback) {
                $cashbacksPorVenda[(int) ($cashback->id_referencia ?? 0)] = (float) ($cashback->valor ?? 0);
            }
        }

        $creditosExpiradosMap = [];
        $expiracaoRefIds = [];
        foreach ($movimentacoes as $mov) {
            if (
                (string) ($mov->tipo ?? '') === MovimentacaoTipoEnum::EXPIRACAO->value
                && (int) ($mov->id_referencia ?? 0) > 0
            ) {
                $expiracaoRefIds[] = (int) $mov->id_referencia;
            }
        }
        if (!empty($expiracaoRefIds)) {
            $placeholders = implode(',', array_fill(0, count($expiracaoRefIds), '?'));
            $creditosOriginais = DB::run(
                "SELECT id, tipo, valor, created_at, expira_em FROM movimentacao WHERE id IN ({$placeholders})",
                $expiracaoRefIds
            );
            foreach ($creditosOriginais as $cred) {
                $creditosExpiradosMap[(int) $cred['id']] = $cred;
            }
        }

        foreach ($movimentacoes as $mov) {
            $tipoValue = (string) ($mov->tipo ?? '');
            $tipoEnum = MovimentacaoTipoEnum::tryFrom($tipoValue);
            $sentido  = (string) ($mov->sentido ?? '');
            $isVendaOperacional = $tipoValue === MovimentacaoTipoEnum::DEBITO->value
                && (string) ($mov->referencia_tipo ?? '') === 'venda';

            if ($isVendaOperacional) {
                $mov->tipo_print = '<span class="badge filled-outlined bg-primary">Gasto</span>';
                $mov->tipo_filter = 'VENDA';
            } elseif ($tipoValue === MovimentacaoTipoEnum::CARGA_INICIAL->value) {
                $mov->tipo_print = MovimentacaoTipoEnum::RECARGA->badge();
                $mov->tipo_filter = MovimentacaoTipoEnum::RECARGA->value;
            } else {
                $mov->tipo_print = $tipoEnum
                ? $tipoEnum->badge()
                : htmlspecialchars((string) ($mov->tipo ?? ''), ENT_QUOTES, 'UTF-8');
                $mov->tipo_filter = $tipoValue;
            }
            $mov->info_icon = '';
            if ($tipoValue === MovimentacaoTipoEnum::EXPIRACAO->value) {
                $idRef    = (int) ($mov->id_referencia ?? 0);
                $credOrig = $idRef > 0 ? ($creditosExpiradosMap[$idRef] ?? null) : null;

                if ($credOrig) {
                    $tipoCredEnum  = MovimentacaoTipoEnum::tryFrom((string) ($credOrig['tipo'] ?? ''));
                    $tipoCredLabel = $tipoCredEnum ? $tipoCredEnum->label() : (string) ($credOrig['tipo'] ?? '');
                    $valorCred     = 'R$ ' . number_format((float) ($credOrig['valor'] ?? 0), 2, ',', '.');
                    $dataCredito   = !empty($credOrig['created_at'])
                        ? datebr((string) $credOrig['created_at'])
                        : '?';
                    $dataExpiracao = !empty($credOrig['expira_em'])
                        ? datebr((string) $credOrig['expira_em'])
                        : '-';
                    $conteudo = 'Mov. #' . $idRef . ' - ' . $tipoCredLabel . ' de ' . $valorCred
                        . ' em ' . $dataCredito . ' | Expirou em ' . $dataExpiracao;
                } else {
                    $conteudo = 'Crédito expirado.';
                }

                $mov->info_icon = '<button type="button" class="btn btn-link p-0 ms-1 text-muted align-middle lh-1"'
                    . ' data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top"'
                    . ' data-bs-custom-class="popover-sm"'
                    . ' data-bs-content="' . htmlspecialchars($conteudo, ENT_QUOTES, 'UTF-8') . '"'
                    . ' tabindex="0"><i class="uil uil-info-circle"></i></button>';
            }
            $mov->sentido_icon = $isVendaOperacional
                ? ''
                : ($sentido === 'CREDITO'
                    ? '<i class="uil uil-arrow-up-right text-success"></i>'
                    : '<i class="uil uil-arrow-down-left text-danger"></i>');
            $mov->is_credito   = $isVendaOperacional ? null : ($sentido === 'CREDITO');
            $mov->valor_print  = $isVendaOperacional
                ? '<small class="text-black-50 fw-normal fst-italic">R$ ' . number_format((float) ($mov->valor ?? 0), 2, ',', '.') . '</small>'
                : 'R$ ' . number_format((float) ($mov->valor ?? 0), 2, ',', '.');
            $mov->saldo_print  = 'R$ ' . number_format((float) ($mov->saldo_posterior ?? 0), 2, ',', '.');
            $mov->data_print   = !empty($mov->created_at)
                ? datetimebr((string) $mov->created_at)
                : '-';
            $mov->forma_print  = $this->formatFormaPagamento((string) ($mov->forma_pagamento ?? ''));
            $mov->observacao_print = trim((string) ($mov->descricao ?? ''));
            if ($isVendaOperacional) {
                $cashbackGerado = round((float) ($cashbacksPorVenda[(int) ($mov->id ?? 0)] ?? 0), 2);
                $valorMinimoFidelidade = round((float) ($regraFidelidadeAtual->valor_minimo_venda ?? 0), 2);
                $contouNaFidelidade = !$regraFidelidadeAtual
                    || $valorMinimoFidelidade <= 0
                    || round((float) ($mov->valor ?? 0), 2) >= $valorMinimoFidelidade;

                if ($cashbackGerado > 0) {
                    $mov->observacao_print = $mov->observacao_print !== ''
                        ? 'Gasto registrado. Cashback de R$ ' . number_format($cashbackGerado, 2, ',', '.') . ' lançado em linha separada. ' . $mov->observacao_print
                        : 'Gasto registrado. Cashback de R$ ' . number_format($cashbackGerado, 2, ',', '.') . ' lançado em linha separada.';
                } else {
                    if ($contouNaFidelidade) {
                        $mov->observacao_print = $mov->observacao_print !== ''
                            ? 'Gasto registrado sem impacto no saldo do cartão. Entrou no acumulado da fidelidade. ' . $mov->observacao_print
                            : 'Gasto registrado sem impacto no saldo do cartão. Entrou no acumulado da fidelidade.';
                    } else {
                        $mov->observacao_print = $mov->observacao_print !== ''
                            ? 'Gasto registrado sem impacto no saldo do cartão. Não entrou na contagem da fidelidade por não atingir o valor mínimo da regra. ' . $mov->observacao_print
                            : 'Gasto registrado sem impacto no saldo do cartão. Não entrou na contagem da fidelidade por não atingir o valor mínimo da regra.';
                    }
                }

                $descricaoBaseGasto = trim((string) ($mov->descricao ?? ''));
                if ($cashbackGerado > 0) {
                    $mov->observacao_print = $descricaoBaseGasto !== ''
                        ? $descricaoBaseGasto . '. Cashback de R$ ' . number_format($cashbackGerado, 2, ',', '.') . ' lançado em linha separada.'
                        : 'Gasto registrado. Cashback de R$ ' . number_format($cashbackGerado, 2, ',', '.') . ' lançado em linha separada.';
                } elseif ($contouNaFidelidade) {
                    $mov->observacao_print = $descricaoBaseGasto !== ''
                        ? $descricaoBaseGasto . ' sem impacto no saldo do cartão. Entrou no acumulado da fidelidade.'
                        : 'Gasto registrado sem impacto no saldo do cartão. Entrou no acumulado da fidelidade.';
                } else {
                    $mov->observacao_print = $descricaoBaseGasto !== ''
                        ? $descricaoBaseGasto . ' sem impacto no saldo do cartão. Não entrou na contagem da fidelidade por não atingir o valor mínimo da regra.'
                        : 'Gasto registrado sem impacto no saldo do cartão. Não entrou na contagem da fidelidade por não atingir o valor mínimo da regra.';
                }
            }
            $mov->can_estornar = $tipoValue !== MovimentacaoTipoEnum::ESTORNO->value
                && empty($estornadas[(int) ($mov->id ?? 0)]);
            $mov->foi_estornada = !empty($estornadas[(int) ($mov->id ?? 0)]);
        }

        return $movimentacoes;
    }

    private function getExtratoTiposFiltro(): array
    {
        return [
            (object) ['value' => 'VENDA', 'label' => 'Gasto'],
            (object) ['value' => MovimentacaoTipoEnum::RECARGA->value, 'label' => MovimentacaoTipoEnum::RECARGA->label()],
            (object) ['value' => MovimentacaoTipoEnum::CASHBACK->value, 'label' => MovimentacaoTipoEnum::CASHBACK->label()],
            (object) ['value' => MovimentacaoTipoEnum::FIDELIDADE->value, 'label' => MovimentacaoTipoEnum::FIDELIDADE->label()],
            (object) ['value' => MovimentacaoTipoEnum::DEBITO->value, 'label' => MovimentacaoTipoEnum::DEBITO->label()],
            (object) ['value' => MovimentacaoTipoEnum::AJUSTE_CREDITO->value, 'label' => MovimentacaoTipoEnum::AJUSTE_CREDITO->label()],
            (object) ['value' => MovimentacaoTipoEnum::AJUSTE_DEBITO->value, 'label' => MovimentacaoTipoEnum::AJUSTE_DEBITO->label()],
            (object) ['value' => MovimentacaoTipoEnum::ESTORNO->value, 'label' => MovimentacaoTipoEnum::ESTORNO->label()],
        ];
    }

    /**
     * Impede novo estorno sobre a mesma movimentação de origem.
     */
    private function movimentacaoJaEstornada(int $idMovimentacao): bool
    {
        if ($idMovimentacao <= 0) {
            return false;
        }

        return Movimentacao::where('mv.referencia_tipo', '=', 'movimentacao')
            ->where('mv.tipo', '=', MovimentacaoTipoEnum::ESTORNO->value)
            ->where('mv.id_referencia', '=', $idMovimentacao)
            ->count() > 0;
    }

    /**
     * Normaliza o código do cartão para 5 dígitos com zeros à esquerda.
     */
    private function normalizeCodigoUnico(string $codigo): string
    {
        $codigo = only_numbers(trim($codigo));

        if ($codigo === '') {
            return '';
        }

        return str_pad($codigo, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica se o código já está em uso por outro cartão.
     */
    private function codigoJaExiste(string $codigo, ?int $ignoreId = null): bool
    {
        $service = new CartaoService();
        $cartao = $service->buscarPorCodigo($codigo);

        if (!$cartao) {
            return false;
        }

        if ($ignoreId !== null && (int) ($cartao->id ?? 0) === $ignoreId) {
            return false;
        }

        return true;
    }

    /**
     * Converte moeda pt-BR em float para operações financeiras.
     */
    private function parseValor(string $raw): float
    {
        return (float) str_replace(',', '.', str_replace('.', '', $raw));
    }

    private function formatFormaPagamento(string $forma): string
    {
        return match ($forma) {
            'pix'      => 'Pix',
            'dinheiro' => 'Dinheiro',
            'credit'   => 'Crédito',
            'debit'    => 'Débito',
            'transfer' => 'Transferência',
            default    => '-',
        };
    }

    private function badgeStatus(string $status): string
    {
        $enum = CartaoStatusEnum::tryFrom(trim($status));

        return $enum ? $enum->badge() : '<span class="badge filled-outlined bg-secondary">Indefinido</span>';
    }

    private function shortValue(string $value, int $length = 18): string
    {
        $value = trim($value);
        if ($value === "") {
            return "-";
        }

        if (mb_strlen($value) <= $length) {
            return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
        }

        $half = max(4, (int) floor(($length - 3) / 2));
        $start = mb_substr($value, 0, $half);
        $end = mb_substr($value, -$half);

        $title = htmlspecialchars($value, ENT_QUOTES, "UTF-8");
        $s     = htmlspecialchars($start, ENT_QUOTES, "UTF-8");
        $e     = htmlspecialchars($end, ENT_QUOTES, "UTF-8");

        return '<span title="' . $title . '">' . $s . '...' . $e . '</span>';
    }

    private function clienteLabel(
        ?string $razao,
        ?string $nome,
        ?string $pessoa,
        ?string $documento,
        ?string $telefone = null
    ): string {
        $principal = trim((string) ($razao ?: $nome)) ?: "Cliente sem identificação";
        $doc = $this->formatDocumento($pessoa, $documento);
        $phone = $this->formatPhone($telefone);

        return $this->buildClientLabel($principal, $doc, $phone);
    }

    private function formatDocumento(?string $pessoa, ?string $documento): string
    {
        $value = only_numbers((string) $documento);

        if ($value === "") {
            return "-";
        }

        if ($pessoa === "F" && strlen($value) === 11) {
            return cpf($value);
        }

        if (strlen($value) === 14) {
            return cnpj($value);
        }

        return htmlspecialchars((string) $documento, ENT_QUOTES, "UTF-8");
    }

    private function formatPhone(?string $telefone): string
    {
        $value = only_numbers((string) $telefone);

        if ($value === "") {
            return "-";
        }

        if (strlen($value) === 11) {
            return preg_replace("/(\\d{2})(\\d{5})(\\d{4})/", "($1) $2-$3", $value) ?: $value;
        }

        if (strlen($value) === 10) {
            return preg_replace("/(\\d{2})(\\d{4})(\\d{4})/", "($1) $2-$3", $value) ?: $value;
        }

        return htmlspecialchars((string) $telefone, ENT_QUOTES, "UTF-8");
    }

    private function buildClientLabel(string $principal, string ...$parts): string
    {
        $items = [$principal];

        foreach ($parts as $part) {
            $value = trim($part);

            if ($value === "" || $value === "-") {
                continue;
            }

            $items[] = $value;
        }

        return implode(" - ", $items);
    }
}

