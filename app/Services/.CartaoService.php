<?php

namespace App\Services;

use App\Core\DB;
use App\Enums\CartaoStatusEnum;
use App\Enums\MovimentacaoTipoEnum;
use App\Enums\MovimentacaoOrigemEnum;
use App\Models\Cartao;
use App\Models\CashbackRegra;
use App\Models\FidelidadeRegra;
use App\Models\Movimentacao;
use RuntimeException;

class CartaoService
{
    /* ------------------------------------------------------------------ */
    /* BUSCA                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Localiza um cartÃƒÂ£o pelo cÃƒÂ³digo principal e, por legado, tambÃƒÂ©m por
     * token NFC/QR. Se a entrada for numÃƒÂ©rica, aceita busca pelo valor sem
     * zeros ÃƒÂ  esquerda para encontrar cartÃƒÂµes como "00001" digitando "1".
     */
    public function buscarPorCodigo(string $codigo): ?Cartao
    {
        $codigo = strtoupper(trim($codigo));

        if ($codigo === '') {
            return null;
        }

        $cartao = Cartao::whereRaw(
            '(ct.codigo_unico = ? OR ct.token_nfc = ? OR ct.token_qr = ?)',
            [$codigo, $codigo, $codigo]
        )->first();

        if ($cartao) {
            return $cartao;
        }

        // "1" encontra "00001": compara pelo valor numÃƒÂ©rico quando o input ÃƒÂ© sÃƒÂ³ dÃƒÂ­gitos
        if (ctype_digit($codigo)) {
            $cartao = Cartao::whereRaw(
                'CAST(ct.codigo_unico AS UNSIGNED) = ?',
                [(int) $codigo]
            )->first();
        }

        return $cartao;
    }

    /* ------------------------------------------------------------------ */
    /* RESUMO DO CARTÃƒÆ’O                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Consolida o resumo financeiro do cartÃƒÂ£o a partir do histÃƒÂ³rico completo
     * de movimentaÃƒÂ§ÃƒÂµes. Os totais sÃƒÂ£o separados por tipo/sentido para o hub
     * bater visualmente com o extrato.
     */
    public function resumo(int $idCartao): object
    {
        $movimentacoes = Movimentacao::where('mv.id_cartao', '=', $idCartao)
            ->orderBy('mv.created_at', 'asc')
            ->get();

        $resumo = (object) [
            'total_carga_inicial' => 0.0,
            'total_recargas'      => 0.0,
            'total_cashback'      => 0.0,
            'total_fidelidade'    => 0.0,
            'total_ajustes'       => 0.0,
            'total_ajustes_credito' => 0.0,
            'total_ajustes_debito'  => 0.0,
            'total_vendas_valor'  => 0.0,
            'total_uso_saldo'     => 0.0,
            'total_estornos'      => 0.0,
            'total_estornos_credito' => 0.0,
            'total_estornos_debito'  => 0.0,
            'quantidade_vendas'   => 0,
            'ticket_medio_vendas' => 0.0,
            'frequencia_vendas_por_semana' => 0.0,
            'frequencia_vendas_por_mes' => 0.0,
            'frequencia_vendas_intervalo_dias' => 0.0,
            'dias_base_vendas' => 0,
            'primeira_venda_em' => null,
            'ultima_movimentacao' => null,
        ];

        foreach ($movimentacoes as $mov) {
            $valor = (float) ($mov->valor ?? 0);
            $tipo  = $mov->tipo ?? '';

            switch ($tipo) {
                case MovimentacaoTipoEnum::CARGA_INICIAL->value:
                    $resumo->total_carga_inicial += $valor;
                    $resumo->total_recargas += $valor;
                    break;
                case MovimentacaoTipoEnum::RECARGA->value:
                    $resumo->total_recargas += $valor;
                    break;
                case MovimentacaoTipoEnum::CASHBACK->value:
                    $resumo->total_cashback += $valor;
                    break;
                case MovimentacaoTipoEnum::FIDELIDADE->value:
                    $resumo->total_fidelidade += $valor;
                    break;
                case MovimentacaoTipoEnum::AJUSTE_CREDITO->value:
                    $resumo->total_ajustes += $valor;
                    $resumo->total_ajustes_credito += $valor;
                    break;
                case MovimentacaoTipoEnum::AJUSTE_DEBITO->value:
                    $resumo->total_ajustes += $valor;
                    $resumo->total_ajustes_debito += $valor;
                    break;
                case MovimentacaoTipoEnum::DEBITO->value:
                    if ($this->isVendaMovimentacao($mov)) {
                        $resumo->total_vendas_valor += $valor;
                        $resumo->quantidade_vendas++;
                        if ($resumo->primeira_venda_em === null && !empty($mov->created_at)) {
                            $resumo->primeira_venda_em = $mov->created_at;
                        }
                    } else {
                        $resumo->total_uso_saldo += $valor;
                    }
                    break;
                case MovimentacaoTipoEnum::ESTORNO->value:
                    $resumo->total_estornos += $valor;
                    if (($mov->sentido ?? '') === 'CREDITO') {
                        $resumo->total_estornos_credito += $valor;
                    } else {
                        $resumo->total_estornos_debito += $valor;
                    }
                    break;
            }

            $resumo->ultima_movimentacao = $mov->created_at;
        }

        if ($resumo->quantidade_vendas > 0) {
            $resumo->ticket_medio_vendas = round($resumo->total_vendas_valor / $resumo->quantidade_vendas, 2);

            if (!empty($resumo->primeira_venda_em)) {
                $hoje = new \DateTimeImmutable(date('Y-m-d'));
                $primeiraVenda = new \DateTimeImmutable(date('Y-m-d', strtotime((string) $resumo->primeira_venda_em)));
                $diasBase = max(1, (int) $primeiraVenda->diff($hoje)->days + 1);

                $resumo->dias_base_vendas = $diasBase;
                $resumo->frequencia_vendas_por_semana = round($resumo->quantidade_vendas / ($diasBase / 7), 2);
                $resumo->frequencia_vendas_por_mes = round($resumo->quantidade_vendas / ($diasBase / 30), 2);
                $resumo->frequencia_vendas_intervalo_dias = round($diasBase / $resumo->quantidade_vendas, 2);
            }
        }

        return $resumo;
    }

    /**
     * Recalcula o saldo do cartÃƒÂ£o a partir do histÃƒÂ³rico de movimentaÃƒÂ§ÃƒÂµes e
     * sincroniza o registro persistido quando houver divergÃƒÂªncia.
     */
    public function reconciliarIndicadores(Cartao $cartao, ?int $updatedBy = null): object
    {
        $movimentacoes = Movimentacao::where('mv.id_cartao', '=', (int) $cartao->id)
            ->orderBy('mv.created_at', 'asc')
            ->orderBy('mv.id', 'asc')
            ->get();

        $saldoCalculado = 0.0;
        $totalVendasCalculado = 0;
        $totalGastoCalculado = 0.0;
        $acumuladoCalculado = 0;
        $valorAcumuladoCalculado = 0.0;
        $regraFidelidadeAtual = $this->buscarRegraFidelidadeVigente();

        foreach ($movimentacoes as $mov) {
            $tipo = (string) ($mov->tipo ?? '');
            $sentido = strtoupper((string) ($mov->sentido ?? ''));
            $valor = (float) ($mov->valor ?? 0);

            if ($this->isVendaMovimentacao($mov)) {
                $saldoCalculado = round($saldoCalculado, 2);
            } elseif ($sentido === 'DEBITO') {
                $saldoCalculado = round($saldoCalculado - $valor, 2);
            } else {
                $saldoCalculado = round($saldoCalculado + $valor, 2);
            }

            if ($tipo === MovimentacaoTipoEnum::DEBITO->value && $this->isVendaMovimentacao($mov)) {
                $totalVendasCalculado++;
                $totalGastoCalculado = round($totalGastoCalculado + $valor, 2);
                $valorAcumuladoCalculado = round($valorAcumuladoCalculado + $valor, 2);
                if ($this->vendaContaParaQuantidadeFidelidade($valor, $regraFidelidadeAtual)) {
                    $acumuladoCalculado++;
                }
            }

            if ($tipo === MovimentacaoTipoEnum::FIDELIDADE->value) {
                $acumuladoCalculado = 0;
                $valorAcumuladoCalculado = 0.0;
            }
        }

        $saldoAtual = round((float) ($cartao->saldo ?? 0), 2);
        $totalVendasAtual = (int) ($cartao->total_vendas ?? 0);
        $totalGastoAtual = round((float) ($cartao->total_gasto ?? 0), 2);
        $acumuladoAtual = (int) ($cartao->acumulado ?? 0);
        $valorAcumuladoAtual = round((float) ($cartao->valor_acumulado ?? 0), 2);

        if (
            $saldoAtual !== $saldoCalculado
            || $totalVendasAtual !== $totalVendasCalculado
            || $totalGastoAtual !== $totalGastoCalculado
            || $acumuladoAtual !== $acumuladoCalculado
            || $valorAcumuladoAtual !== $valorAcumuladoCalculado
        ) {
            Cartao::updateBy((int) $cartao->id, [
                'saldo' => $saldoCalculado,
                'total_vendas' => $totalVendasCalculado,
                'total_gasto' => $totalGastoCalculado,
                'acumulado' => $acumuladoCalculado,
                'valor_acumulado' => $valorAcumuladoCalculado,
                'updated_by' => $updatedBy,
            ]);
        }

        return (object) [
            'saldo' => $saldoCalculado,
            'total_vendas' => $totalVendasCalculado,
            'total_gasto' => $totalGastoCalculado,
            'acumulado' => $acumuladoCalculado,
            'valor_acumulado' => $valorAcumuladoCalculado,
        ];
    }

    /**
     * Sincroniza o status do cartão com a validade antes do hub operar.
     */
    public function sincronizarStatusPorValidade(Cartao $cartao, ?int $updatedBy = null): Cartao
    {
        $validade = trim((string) ($cartao->validade ?? ''));
        $statusAtual = CartaoStatusEnum::tryFrom((string) ($cartao->status ?? ''));

        if (
            $validade !== ''
            && $validade < date('Y-m-d')
            && $statusAtual !== CartaoStatusEnum::VENCIDO
            && $statusAtual !== CartaoStatusEnum::CANCELADO
        ) {
            Cartao::updateBy((int) $cartao->id, [
                'status' => CartaoStatusEnum::VENCIDO->value,
                'updated_by' => $updatedBy,
            ]);

            $cartao->status = CartaoStatusEnum::VENCIDO->value;
        }

        return $cartao;
    }

    /* ------------------------------------------------------------------ */
    /* OPERAÃƒâ€¡Ãƒâ€¢ES FINANCEIRAS                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Credita saldo no cartÃƒÂ£o. O tipo padrÃƒÂ£o ÃƒÂ© RECARGA, mas o mÃƒÂ©todo aceita
     * outros tipos de crÃƒÂ©dito compatÃƒÂ­veis para reaproveitar a mesma rotina.
     */
    public function recarregar(
        Cartao $cartao,
        float $valor,
        int $idOperador,
        string $origem = 'admin',
        MovimentacaoTipoEnum $tipo = MovimentacaoTipoEnum::RECARGA,
        ?string $formaPagamento = null,
        ?string $descricao = null,
        ?int $createdBy = null
    ): object {
        if ($valor <= 0) {
            throw new RuntimeException('O valor da recarga deve ser maior que zero.');
        }

        $this->validarCartaoOperavel($cartao);

        return DB::transaction(
            function () use ($cartao, $valor, $idOperador, $origem, $tipo, $formaPagamento, $descricao, $createdBy) {
                $saldoAnterior  = (float) ($cartao->saldo ?? 0);
                $saldoPosterior = round($saldoAnterior + $valor, 2);

                Cartao::updateBy((int) $cartao->id, ['saldo' => $saldoPosterior]);

                return $this->registrarMovimentacao(
                    cartao:         $cartao,
                    tipo:           $tipo,
                    valor:          $valor,
                    saldoAnterior:  $saldoAnterior,
                    saldoPosterior: $saldoPosterior,
                    origem:         $origem,
                    idOperador:     $idOperador,
                    formaPagamento: $formaPagamento,
                    descricao:      $descricao,
                    createdBy:      $createdBy
                );
            }
        );
    }

    /**
     * Registra um gasto operacional do cartÃƒÂ£o e dispara, quando
     * aplicÃƒÂ¡vel, cashback e fidelidade conforme as regras vigentes.
     */
    public function lancarVenda(
        Cartao $cartao,
        float $valorVenda,
        int $idOperador,
        ?string $descricao = null,
        ?int $createdBy = null
    ): object {
        if ($valorVenda <= 0) {
            throw new RuntimeException('O valor do gasto deve ser maior que zero.');
        }

        $this->validarCartaoOperavel($cartao);

        $resultado = (object) [
            'cashback_aplicado'  => 0.0,
            'fidelidade_aplicada' => false,
            'fidelidade_valor'   => 0.0,
            'total_vendas'       => 0,
        ];

        $agora = date('Y-m-d H:i:s');

        // Incrementa total histÃƒÂ³rico e acumulado do ciclo atual
        $novoTotalVendas = (int) ($cartao->total_vendas ?? 0) + 1;
        $novoAcumulado   = (int) ($cartao->acumulado   ?? 0) + 1;
        $novoTotalGasto  = round((float) ($cartao->total_gasto ?? 0) + $valorVenda, 2);
        Cartao::updateBy((int) $cartao->id, [
            'total_vendas' => $novoTotalVendas,
            'acumulado'    => $novoAcumulado,
            'total_gasto'  => $novoTotalGasto,
        ]);
        $resultado->total_vendas = $novoTotalVendas;

        // Cashback: primeira regra ativa que se aplica ao valor
        $regrasCashback = CashbackRegra::where('cr.ativo', '=', 1)
            ->whereRaw('(cr.data_inicio IS NULL OR cr.data_inicio <= ?)', [$agora])
            ->whereRaw('(cr.data_fim IS NULL OR cr.data_fim >= ?)', [$agora])
            ->orderBy('cr.id', 'desc')
            ->get();

        foreach ($regrasCashback as $regra) {
            $minimo = (float) ($regra->valor_minimo_recarga ?? 0);
            if ($minimo > 0 && $valorVenda < $minimo) {
                continue;
            }

            $valorCashback = $regra->tipo === 'PERCENTUAL'
                ? round($valorVenda * ((float) $regra->valor / 100), 2)
                : (float) $regra->valor;

            if ($valorCashback > 0) {
                if ($regra->tipo === 'PERCENTUAL') {
                    $pct = rtrim(rtrim(number_format((float) $regra->valor, 2, ',', '.'), '0'), ',');
                    $descCashback = 'Gasto R$ ' . number_format($valorVenda, 2, ',', '.') . ' (' . $pct . '%)';
                } else {
                    $fixo = number_format((float) $regra->valor, 2, ',', '.');
                    $descCashback = 'Gasto R$ ' . number_format($valorVenda, 2, ',', '.') . ' (fixo R$ ' . $fixo . ')';
                }

                if ($descricao !== null && $descricao !== '') {
                    $descCashback .= ' Ã‚Â· ' . $descricao;
                }

                $cartaoFresh = Cartao::find((int) $cartao->id);
                if ($cartaoFresh) {
                    $this->aplicarCashback(
                        cartao:               $cartaoFresh,
                        valorCashback:        $valorCashback,
                        idMovimentacaoOrigem: null,
                        createdBy:            $createdBy,
                        descricao:            $descCashback,
                        idOperador:           $idOperador,
                        idCashbackRegra:      (int) $regra->id
                    );
                }
                $resultado->cashback_aplicado = $valorCashback;
            }

            break;
        }

        // Fidelidade: verifica se o total de gastos atingiu o gatilho
        $regraFidelidade = FidelidadeRegra::where('fr.ativo', '=', 1)
            ->whereRaw('(fr.data_inicio IS NULL OR fr.data_inicio <= ?)', [$agora])
            ->whereRaw('(fr.data_fim IS NULL OR fr.data_fim >= ?)', [$agora])
            ->orderBy('fr.id', 'desc')
            ->first();

        if ($regraFidelidade) {
            $gatilho = (int) ($regraFidelidade->quantidade_vendas ?? 0);
            $valorFidelidade = (float) ($regraFidelidade->valor_saldo ?? 0);

            if ($gatilho > 0 && $valorFidelidade > 0 && $novoAcumulado % $gatilho === 0) {
                $cartaoFresh = Cartao::find((int) $cartao->id);
                if ($cartaoFresh) {
                    $descFidelidade = "Bônus a cada {$gatilho} gasto" . ($gatilho > 1 ? 's' : '')
                        . " (gasto #{$novoTotalVendas})";
                    $this->aplicarFidelidade($cartaoFresh, $valorFidelidade, $createdBy, $descFidelidade);
                }
                // Zera apenas o acumulado do ciclo; total_vendas histÃƒÂ³rico permanece
                Cartao::updateBy((int) $cartao->id, ['acumulado' => 0]);
                $resultado->fidelidade_aplicada = true;
                $resultado->fidelidade_valor    = $valorFidelidade;
            }
        }

        return $resultado;
    }

    public function lancarVendaOperacional(
        Cartao $cartao,
        float $valorVenda,
        int $idOperador,
        ?string $descricao = null,
        ?int $createdBy = null
    ): object {
        if ($valorVenda <= 0) {
            throw new RuntimeException('O valor do gasto deve ser maior que zero.');
        }

        $this->validarCartaoOperavel($cartao);

        return DB::transaction(function () use ($cartao, $valorVenda, $idOperador, $descricao, $createdBy) {
            $resultado = (object) [
                'cashback_aplicado'   => 0.0,
                'fidelidade_aplicada' => false,
                'fidelidade_valor'    => 0.0,
                'total_vendas'        => 0,
            ];

            $agora = date('Y-m-d H:i:s');
            $saldoAtual = (float) ($cartao->saldo ?? 0);
            $createdAtVenda = $agora;
            $createdAtCashback = date('Y-m-d H:i:s', strtotime($createdAtVenda . ' +1 second'));
            $createdAtFidelidade = date('Y-m-d H:i:s', strtotime($createdAtVenda . ' +2 second'));

            $movVenda = $this->registrarMovimentacao(
                cartao:         $cartao,
                tipo:           MovimentacaoTipoEnum::DEBITO,
                valor:          $valorVenda,
                saldoAnterior:  $saldoAtual,
                saldoPosterior: $saldoAtual,
                origem:         MovimentacaoOrigemEnum::ADMIN->value,
                idOperador:     $idOperador,
                referenciaTipo: 'venda',
                descricao:      $descricao,
                createdBy:      $createdBy,
                createdAt:      $createdAtVenda
            );

            $this->atualizarDescricaoMovimentacaoVenda($movVenda, $descricao);

            $novoTotalVendas = (int) ($cartao->total_vendas ?? 0) + 1;
            $novoAcumulado   = (int) ($cartao->acumulado ?? 0);
            $novoValorAcumulado = round((float) ($cartao->valor_acumulado ?? 0) + $valorVenda, 2);
            $novoTotalGasto  = round((float) ($cartao->total_gasto ?? 0) + $valorVenda, 2);

            Cartao::updateBy((int) $cartao->id, [
                'total_vendas' => $novoTotalVendas,
                'acumulado'    => $novoAcumulado,
                'valor_acumulado' => $novoValorAcumulado,
                'total_gasto'  => $novoTotalGasto,
            ]);

            $resultado->total_vendas = $novoTotalVendas;

            $regrasCashback = CashbackRegra::where('cr.ativo', '=', 1)
                ->whereRaw('(cr.data_inicio IS NULL OR cr.data_inicio <= ?)', [$agora])
                ->whereRaw('(cr.data_fim IS NULL OR cr.data_fim >= ?)', [$agora])
                ->orderBy('cr.id', 'desc')
                ->get();

            foreach ($regrasCashback as $regra) {
                $minimo = (float) ($regra->valor_minimo_recarga ?? 0);
                if ($minimo > 0 && $valorVenda < $minimo) {
                    continue;
                }

                $valorCashback = $regra->tipo === 'PERCENTUAL'
                    ? round($valorVenda * ((float) $regra->valor / 100), 2)
                    : (float) $regra->valor;

                if ($valorCashback <= 0) {
                    break;
                }

                if ($regra->tipo === 'PERCENTUAL') {
                    $pct = rtrim(rtrim(number_format((float) $regra->valor, 2, ',', '.'), '0'), ',');
                    $descCashback = 'Gasto R$ ' . number_format($valorVenda, 2, ',', '.') . ' (' . $pct . '%)';
                } else {
                    $fixo = number_format((float) $regra->valor, 2, ',', '.');
                    $descCashback = 'Gasto R$ ' . number_format($valorVenda, 2, ',', '.') . ' (fixo R$ ' . $fixo . ')';
                }

                if ($descricao !== null && $descricao !== '') {
                    $descCashback .= ' Ãƒâ€šÃ‚Â· ' . $descricao;
                }

                $cartaoFresh = Cartao::find((int) $cartao->id);
                if ($cartaoFresh) {
                    $movCashback = $this->aplicarCashback(
                        cartao:               $cartaoFresh,
                        valorCashback:        $valorCashback,
                        idMovimentacaoOrigem: (int) ($movVenda->id ?? 0),
                        createdBy:            $createdBy,
                        descricao:            $descCashback,
                        idOperador:           $idOperador,
                        idCashbackRegra:      (int) $regra->id,
                        createdAt:            $createdAtCashback
                    );

                    $this->atualizarDescricaoMovimentacaoBonus(
                        $movCashback,
                        'Cashback do Gasto #' . (int) ($movVenda->id ?? 0) . ' (R$ ' . number_format($valorVenda, 2, ',', '.') . ')',
                        $descricao
                    );
                }

                $resultado->cashback_aplicado = $valorCashback;
                break;
            }

            $regraFidelidade = FidelidadeRegra::where('fr.ativo', '=', 1)
                ->whereRaw('(fr.data_inicio IS NULL OR fr.data_inicio <= ?)', [$agora])
                ->whereRaw('(fr.data_fim IS NULL OR fr.data_fim >= ?)', [$agora])
                ->orderBy('fr.id', 'desc')
                ->first();

            if ($this->vendaContaParaQuantidadeFidelidade($valorVenda, $regraFidelidade)) {
                $novoAcumulado++;
                Cartao::updateBy((int) $cartao->id, [
                    'acumulado' => $novoAcumulado,
                ]);
            }

            if ($regraFidelidade) {
                $valorFidelidade = (float) ($regraFidelidade->valor_saldo ?? 0);

                if ($valorFidelidade > 0) {
                    $cartaoFresh = Cartao::find((int) $cartao->id);

                    if ($cartaoFresh && $this->cartaoAtingiuRegraFidelidade($cartaoFresh, $regraFidelidade)) {
                        $gatilhoUsado = $this->resolverGatilhoFidelidadeAtingido($cartaoFresh, $regraFidelidade);
                        $descFidelidade = $this->descricaoGatilhoFidelidade(
                            $regraFidelidade,
                            $gatilhoUsado,
                            $novoTotalVendas
                        );

                        $movFidelidade = $this->aplicarFidelidade(
                            $cartaoFresh,
                            $valorFidelidade,
                            $createdBy,
                            $descFidelidade,
                            (int) ($movVenda->id ?? 0),
                            $createdAtFidelidade
                        );

                        $this->atualizarDescricaoMovimentacaoBonus(
                            $movFidelidade,
                            'Bônus de Fidelidade do Gasto #' . (int) ($movVenda->id ?? 0),
                            $descricao
                        );

                        $cartaoAtualizado = Cartao::find((int) $cartao->id);
                        if ($cartaoAtualizado) {
                            $this->consumirGatilhoFidelidade($cartaoAtualizado, $regraFidelidade, $gatilhoUsado);
                        }

                        $resultado->fidelidade_aplicada = true;
                        $resultado->fidelidade_valor    = $valorFidelidade;
                    }
                }

                $gatilho = 0;

                if ($gatilho > 0 && $valorFidelidade > 0 && $novoAcumulado % $gatilho === 0) {
                    $cartaoFresh = Cartao::find((int) $cartao->id);
                    if ($cartaoFresh) {
                        $descFidelidade = "Bônus a cada {$gatilho} gasto" . ($gatilho > 1 ? 's' : '')
                            . " (gasto #{$novoTotalVendas})";
                        $this->aplicarFidelidade($cartaoFresh, $valorFidelidade, $createdBy, $descFidelidade);
                    }

                    Cartao::updateBy((int) $cartao->id, ['acumulado' => 0]);
                    $resultado->fidelidade_aplicada = true;
                    $resultado->fidelidade_valor    = $valorFidelidade;
                }
            }

            return $resultado;
        });
    }

    /**
     * Debita saldo do cartÃƒÂ£o como consumo simples, sem avaliar regras de
     * cashback/fidelidade.
     */
    public function registrarConsumo(
        Cartao $cartao,
        float $valor,
        int $idOperador,
        string $origem = 'admin',
        ?string $descricao = null,
        ?int $createdBy = null
    ): object {
        if ($valor <= 0) {
            throw new RuntimeException('O valor do consumo deve ser maior que zero.');
        }

        $this->validarCartaoOperavel($cartao, $valor);

        return DB::transaction(function () use ($cartao, $valor, $idOperador, $origem, $descricao, $createdBy) {
            $saldoAnterior  = (float) ($cartao->saldo ?? 0);
            $saldoPosterior = round($saldoAnterior - $valor, 2);

            Cartao::updateBy((int) $cartao->id, ['saldo' => $saldoPosterior]);

            return $this->registrarMovimentacao(
                cartao:         $cartao,
                tipo:           MovimentacaoTipoEnum::DEBITO,
                valor:          $valor,
                saldoAnterior:  $saldoAnterior,
                saldoPosterior: $saldoPosterior,
                origem:         $origem,
                idOperador:     $idOperador,
                descricao:      $descricao,
                createdBy:      $createdBy
            );
        });
    }

    /**
     * Credita cashback no cartÃƒÂ£o, vinculando a regra ou a movimentaÃƒÂ§ÃƒÂ£o de
     * origem quando essas referÃƒÂªncias estiverem disponÃƒÂ­veis.
     */
    public function aplicarCashback(
        Cartao $cartao,
        float $valorCashback,
        ?int $idMovimentacaoOrigem = null,
        ?int $createdBy = null,
        ?string $descricao = null,
        ?int $idOperador = null,
        ?int $idCashbackRegra = null,
        ?string $createdAt = null
    ): object {
        if ($valorCashback <= 0) {
            throw new RuntimeException('O valor do cashback deve ser maior que zero.');
        }

        $this->validarCartaoOperavel($cartao);

        return DB::transaction(
            function () use (
                $cartao,
                $valorCashback,
                $idMovimentacaoOrigem,
                $createdBy,
                $descricao,
                $idOperador,
                $idCashbackRegra,
                $createdAt
            ) {
                $saldoAnterior  = (float) ($cartao->saldo ?? 0);
                $saldoPosterior = round($saldoAnterior + $valorCashback, 2);

                Cartao::updateBy((int) $cartao->id, ['saldo' => $saldoPosterior]);

                if ($idCashbackRegra !== null) {
                    $refTipo = 'cashback_regra';
                    $refId   = $idCashbackRegra;
                } elseif ($idMovimentacaoOrigem !== null) {
                    $refTipo = 'movimentacao';
                    $refId   = $idMovimentacaoOrigem;
                } else {
                    $refTipo = null;
                    $refId   = null;
                }

                return $this->registrarMovimentacao(
                    cartao:         $cartao,
                    tipo:           MovimentacaoTipoEnum::CASHBACK,
                    valor:          $valorCashback,
                    saldoAnterior:  $saldoAnterior,
                    saldoPosterior: $saldoPosterior,
                    origem:         MovimentacaoOrigemEnum::SISTEMA->value,
                    idOperador:     $idOperador,
                    referenciaTipo: $refTipo,
                    idReferencia:   $refId,
                    descricao:      $descricao,
                    createdBy:      $createdBy,
                    createdAt:      $createdAt
                );
            }
        );
    }

    /**
     * Credita saldo de fidelidade no cartÃƒÂ£o.
     */
    public function aplicarFidelidade(
        Cartao $cartao,
        float $valorSaldo,
        ?int $createdBy = null,
        ?string $descricao = null,
        ?int $idMovimentacaoOrigem = null,
        ?string $createdAt = null
    ): object {
        if ($valorSaldo <= 0) {
            throw new RuntimeException('O valor do crÃƒÂ©dito de fidelidade deve ser maior que zero.');
        }

        $this->validarCartaoOperavel($cartao);

        return DB::transaction(function () use ($cartao, $valorSaldo, $createdBy, $descricao, $idMovimentacaoOrigem, $createdAt) {
            $saldoAnterior  = (float) ($cartao->saldo ?? 0);
            $saldoPosterior = round($saldoAnterior + $valorSaldo, 2);

            Cartao::updateBy((int) $cartao->id, ['saldo' => $saldoPosterior]);

            return $this->registrarMovimentacao(
                cartao:         $cartao,
                tipo:           MovimentacaoTipoEnum::FIDELIDADE,
                valor:          $valorSaldo,
                saldoAnterior:  $saldoAnterior,
                saldoPosterior: $saldoPosterior,
                origem:         MovimentacaoOrigemEnum::SISTEMA->value,
                idOperador:     null,
                referenciaTipo: $idMovimentacaoOrigem !== null ? 'movimentacao' : null,
                idReferencia:   $idMovimentacaoOrigem,
                createdBy:      $createdBy,
                descricao:      $descricao,
                createdAt:      $createdAt
            );
        });
    }

    /**
     * Estorna uma movimentaÃƒÂ§ÃƒÂ£o anterior preservando rastreabilidade.
     * O estorno sempre nasce apontando para a movimentaÃƒÂ§ÃƒÂ£o original via
     * referencia_tipo/id_referencia e com sentido inverso ao evento estornado.
     */
    public function estornar(
        Cartao $cartao,
        Movimentacao $movOrigem,
        int $idOperador,
        string $descricao,
        ?int $createdBy = null
    ): object {
        if (trim($descricao) === '') {
            throw new RuntimeException('Informe o motivo do estorno.');
        }

        $valorEstorno = (float) ($movOrigem->valor ?? 0);
        if ($valorEstorno <= 0) {
            throw new RuntimeException('MovimentaÃƒÂ§ÃƒÂ£o de origem invÃƒÂ¡lida para estorno.');
        }

        $sentidoOrigem = $movOrigem->sentido ?? '';

        return DB::transaction(function () use (
            $cartao,
            $movOrigem,
            $valorEstorno,
            $sentidoOrigem,
            $idOperador,
            $descricao,
            $createdBy
        ) {
            $saldoAnterior = (float) ($cartao->saldo ?? 0);

            if ($sentidoOrigem === 'DEBITO') {
                $saldoPosterior = round($saldoAnterior + $valorEstorno, 2);
            } else {
                if ($saldoAnterior < $valorEstorno) {
                    throw new RuntimeException('Saldo insuficiente para estornar este crÃƒÂ©dito.');
                }
                $saldoPosterior = round($saldoAnterior - $valorEstorno, 2);
            }

            Cartao::updateBy((int) $cartao->id, ['saldo' => $saldoPosterior]);

            return $this->registrarMovimentacao(
                cartao:         $cartao,
                tipo:           MovimentacaoTipoEnum::ESTORNO,
                valor:          $valorEstorno,
                saldoAnterior:  $saldoAnterior,
                saldoPosterior: $saldoPosterior,
                origem:         MovimentacaoOrigemEnum::ADMIN->value,
                idOperador:     $idOperador,
                sentido:        $sentidoOrigem === 'DEBITO' ? 'CREDITO' : 'DEBITO',
                referenciaTipo: 'movimentacao',
                idReferencia:   (int) $movOrigem->id,
                descricao:      $descricao,
                createdBy:      $createdBy
            );
        });
    }

    /**
     * Ajuste administrativo manual. Usa tipos distintos para crÃƒÂ©dito e dÃƒÂ©bito
     * para deixar o extrato e os resumos mais auditÃƒÂ¡veis.
     */
    public function ajustar(
        Cartao $cartao,
        float $valor,
        string $sentido,
        int $idOperador,
        string $descricao,
        ?int $createdBy = null
    ): object {
        if ($valor <= 0) {
            throw new RuntimeException('O valor do ajuste deve ser maior que zero.');
        }

        if (trim($descricao) === '') {
            throw new RuntimeException('Informe o motivo do ajuste.');
        }

        $sentido = strtoupper(trim($sentido));
        if (!in_array($sentido, ['CREDITO', 'DEBITO'], true)) {
            throw new RuntimeException('Sentido do ajuste invÃƒÂ¡lido.');
        }

        $tipo = $sentido === 'CREDITO'
            ? MovimentacaoTipoEnum::AJUSTE_CREDITO
            : MovimentacaoTipoEnum::AJUSTE_DEBITO;

        $this->validarCartaoOperavel($cartao, $sentido === 'DEBITO' ? $valor : 0);

        return DB::transaction(
            function () use ($cartao, $valor, $sentido, $tipo, $idOperador, $descricao, $createdBy) {
                $saldoAnterior  = (float) ($cartao->saldo ?? 0);
                $saldoPosterior = $sentido === 'CREDITO'
                    ? round($saldoAnterior + $valor, 2)
                    : round($saldoAnterior - $valor, 2);

                Cartao::updateBy((int) $cartao->id, ['saldo' => $saldoPosterior]);

                return $this->registrarMovimentacao(
                    cartao:         $cartao,
                    tipo:           $tipo,
                    valor:          $valor,
                    saldoAnterior:  $saldoAnterior,
                    saldoPosterior: $saldoPosterior,
                    origem:         MovimentacaoOrigemEnum::ADMIN->value,
                    idOperador:     $idOperador,
                    descricao:      $descricao,
                    createdBy:      $createdBy
                );
            }
        );
    }

    /* ------------------------------------------------------------------ */
    /* BLOQUEIO / DESBLOQUEIO                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Bloqueia o cartÃƒÂ£o e registra metadados mÃƒÂ­nimos do bloqueio.
     */
    public function bloquear(Cartao $cartao, int $idOperador, string $motivo, ?int $updatedBy = null): void
    {
        $status = CartaoStatusEnum::tryFrom((string) ($cartao->status ?? ''));

        if ($status === CartaoStatusEnum::BLOQUEADO) {
            throw new RuntimeException('CartÃƒÂ£o jÃƒÂ¡ estÃƒÂ¡ bloqueado.');
        }

        if (!in_array($status, [CartaoStatusEnum::ATIVO], true)) {
            throw new RuntimeException('Apenas cartÃƒÂµes ativos podem ser bloqueados.');
        }

        if (trim($motivo) === '') {
            throw new RuntimeException('Informe o motivo do bloqueio.');
        }

        Cartao::updateBy((int) $cartao->id, [
            'status'          => CartaoStatusEnum::BLOQUEADO->value,
            'data_bloqueio'   => date('Y-m-d H:i:s'),
            'motivo_bloqueio' => trim($motivo),
            'updated_by'      => $updatedBy ?? $idOperador,
        ]);
    }

    /**
     * Reabre um cartÃƒÂ£o previamente bloqueado.
     */
    public function desbloquear(Cartao $cartao, int $idOperador, ?int $updatedBy = null): void
    {
        $status = CartaoStatusEnum::tryFrom((string) ($cartao->status ?? ''));

        if ($status !== CartaoStatusEnum::BLOQUEADO) {
            throw new RuntimeException('CartÃƒÂ£o nÃƒÂ£o estÃƒÂ¡ bloqueado.');
        }

        Cartao::updateBy((int) $cartao->id, [
            'status'     => CartaoStatusEnum::ATIVO->value,
            'updated_by' => $updatedBy ?? $idOperador,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* VALIDAÃƒâ€¡Ãƒâ€¢ES                                                          */
    /* ------------------------------------------------------------------ */

    public function validarCartaoOperavel(Cartao $cartao, float $valorDebito = 0): void
    {
        $status = CartaoStatusEnum::tryFrom((string) ($cartao->status ?? ''));

        if ($status === null) {
            throw new RuntimeException('Status do cartÃƒÂ£o invÃƒÂ¡lido.');
        }

        if ($status !== CartaoStatusEnum::ATIVO) {
            $label = $status->label();
            throw new RuntimeException("CartÃƒÂ£o nÃƒÂ£o pode operar: status atual ÃƒÂ© \"{$label}\".");
        }

        $validade = $cartao->validade ?? null;
        if ($validade && date('Y-m-d') > $validade) {
            throw new RuntimeException('CartÃƒÂ£o vencido.');
        }

        if ($valorDebito > 0) {
            $saldo = (float) ($cartao->saldo ?? 0);
            if ($saldo < $valorDebito) {
                throw new RuntimeException(
                    sprintf(
                        'Saldo insuficiente. DisponÃƒÂ­vel: R$ %s | Solicitado: R$ %s',
                        number_format($saldo, 2, ',', '.'),
                        number_format($valorDebito, 2, ',', '.')
                    )
                );
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* INTERNO                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Cria a linha definitiva de movimentaÃƒÂ§ÃƒÂ£o. O parÃƒÂ¢metro $sentido permite
     * sobrescrever o sentido default do enum em casos especiais, como estorno.
     */
    private function registrarMovimentacao(
        Cartao $cartao,
        MovimentacaoTipoEnum $tipo,
        float $valor,
        float $saldoAnterior,
        float $saldoPosterior,
        string $origem,
        ?int $idOperador = null,
        ?string $sentido = null,
        ?string $referenciaTipo = null,
        ?int $idReferencia = null,
        ?string $formaPagamento = null,
        ?string $descricao = null,
        ?int $createdBy = null,
        ?string $createdAt = null
    ): object {
        $agora = $createdAt ?: date('Y-m-d H:i:s');

        return Movimentacao::create([
            'id_cartao'       => (int) $cartao->id,
            'tipo'            => $tipo->value,
            'sentido'         => $sentido ?: $tipo->sentido(),
            'valor'           => $valor,
            'saldo_anterior'  => $saldoAnterior,
            'saldo_posterior' => $saldoPosterior,
            'origem'          => $origem,
            'id_operador'     => $idOperador,
            'referencia_tipo' => $referenciaTipo,
            'id_referencia'   => $idReferencia,
            'forma_pagamento' => $formaPagamento,
            'descricao'       => ($descricao !== null && $descricao !== '') ? $descricao : null,
            'created_by'      => $createdBy,
            'created_at'      => $agora,
        ]);
    }

    private function isVendaMovimentacao(object $mov): bool
    {
        return (string) ($mov->tipo ?? '') === MovimentacaoTipoEnum::DEBITO->value
            && (string) ($mov->referencia_tipo ?? '') === 'venda';
    }

    private function buscarRegraFidelidadeVigente(): ?object
    {
        $agora = date('Y-m-d H:i:s');

        return FidelidadeRegra::where('fr.ativo', '=', 1)
            ->whereRaw('(fr.data_inicio IS NULL OR fr.data_inicio <= ?)', [$agora])
            ->whereRaw('(fr.data_fim IS NULL OR fr.data_fim >= ?)', [$agora])
            ->orderBy('fr.id', 'desc')
            ->first();
    }

    private function vendaContaParaQuantidadeFidelidade(float $valorVenda, ?object $regra): bool
    {
        if (!$regra) {
            return true;
        }

        $valorMinimoVenda = round((float) ($regra->valor_minimo_venda ?? 0), 2);
        if ($valorMinimoVenda <= 0) {
            return true;
        }

        return round($valorVenda, 2) >= $valorMinimoVenda;
    }

    private function cartaoAtingiuRegraFidelidade(Cartao $cartao, object $regra): bool
    {
        $gatilhoVendas = (int) ($regra->quantidade_vendas ?? 0);
        $gatilhoValor = round((float) ($regra->valor_acumulado_minimo ?? 0), 2);
        $acumulado = (int) ($cartao->acumulado ?? 0);
        $valorAcumulado = round((float) ($cartao->valor_acumulado ?? 0), 2);

        $atingiuVendas = $gatilhoVendas > 0 && $acumulado >= $gatilhoVendas;
        $atingiuValor = $gatilhoValor > 0 && $valorAcumulado >= $gatilhoValor;

        return $atingiuVendas || $atingiuValor;
    }

    private function resolverGatilhoFidelidadeAtingido(Cartao $cartao, object $regra): string
    {
        $gatilhoVendas = (int) ($regra->quantidade_vendas ?? 0);
        $gatilhoValor = round((float) ($regra->valor_acumulado_minimo ?? 0), 2);
        $acumulado = (int) ($cartao->acumulado ?? 0);
        $valorAcumulado = round((float) ($cartao->valor_acumulado ?? 0), 2);

        $atingiuVendas = $gatilhoVendas > 0 && $acumulado >= $gatilhoVendas;
        $atingiuValor = $gatilhoValor > 0 && $valorAcumulado >= $gatilhoValor;

        if ($atingiuVendas && !$atingiuValor) {
            return 'vendas';
        }

        if ($atingiuValor && !$atingiuVendas) {
            return 'valor';
        }

        if ($atingiuVendas && $atingiuValor) {
            $ratioVendas = $gatilhoVendas > 0 ? $acumulado / $gatilhoVendas : PHP_FLOAT_MAX;
            $ratioValor = $gatilhoValor > 0 ? $valorAcumulado / $gatilhoValor : PHP_FLOAT_MAX;

            return $ratioVendas <= $ratioValor ? 'vendas' : 'valor';
        }

        return 'vendas';
    }

    private function consumirGatilhoFidelidade(Cartao $cartao, object $regra, string $gatilhoUsado): void
    {
        if ($gatilhoUsado === 'valor') {
            $gatilhoValor = round((float) ($regra->valor_acumulado_minimo ?? 0), 2);
            $valorAtual = round((float) ($cartao->valor_acumulado ?? 0), 2);

            Cartao::updateBy((int) $cartao->id, [
                'valor_acumulado' => max(0, round($valorAtual - $gatilhoValor, 2)),
            ]);
            return;
        }

        $gatilhoVendas = (int) ($regra->quantidade_vendas ?? 0);
        $acumuladoAtual = (int) ($cartao->acumulado ?? 0);

        Cartao::updateBy((int) $cartao->id, [
            'acumulado' => max(0, $acumuladoAtual - $gatilhoVendas),
        ]);
    }

    private function descricaoGatilhoFidelidade(object $regra, string $gatilhoUsado, int $totalVendas): string
    {
        if ($gatilhoUsado === 'valor') {
            $gatilhoValor = round((float) ($regra->valor_acumulado_minimo ?? 0), 2);

            return 'Bonus por fidelidade a cada R$ '
                . number_format($gatilhoValor, 2, ',', '.')
                . ' acumulados (gasto #'
                . $totalVendas
                . ')';
        }

        $gatilhoVendas = (int) ($regra->quantidade_vendas ?? 0);
        $valorMinimoVenda = round((float) ($regra->valor_minimo_venda ?? 0), 2);

        $descricao = 'Bonus por fidelidade a cada '
            . $gatilhoVendas
            . ' gasto'
            . ($gatilhoVendas > 1 ? 's' : '')
            . ' (gasto #'
            . $totalVendas
            . ')';

        if ($valorMinimoVenda > 0) {
            $descricao .= ' com valor minimo de R$ ' . number_format($valorMinimoVenda, 2, ',', '.');
        }

        return $descricao;
    }

    private function atualizarDescricaoMovimentacaoVenda(?object $movVenda, ?string $descricaoOriginal = null): void
    {
        $idMovVenda = (int) ($movVenda->id ?? 0);
        if ($idMovVenda <= 0) {
            return;
        }

        $descricao = 'Gasto #' . $idMovVenda;
        $descricaoOriginal = trim((string) ($descricaoOriginal ?? ''));
        if ($descricaoOriginal !== '') {
            $descricao .= ' - ' . $descricaoOriginal;
        }

        Movimentacao::updateBy($idMovVenda, [
            'descricao' => $descricao,
        ]);

        $movVenda->descricao = $descricao;
    }

    private function atualizarDescricaoMovimentacaoBonus(?object $movBonus, string $prefixo, ?string $descricaoOriginal = null): void
    {
        $idMovBonus = (int) ($movBonus->id ?? 0);
        if ($idMovBonus <= 0) {
            return;
        }

        $descricao = trim($prefixo);
        $descricaoOriginal = trim((string) ($descricaoOriginal ?? ''));
        if ($descricaoOriginal !== '') {
            $descricao .= ' - ' . $descricaoOriginal;
        }

        Movimentacao::updateBy($idMovBonus, [
            'descricao' => $descricao,
        ]);

        $movBonus->descricao = $descricao;
    }

}


