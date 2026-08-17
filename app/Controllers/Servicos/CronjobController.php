<?php

namespace App\Controllers\Servicos;

use App\Core\Config;
use App\Core\Controller;
use App\Core\DB;
use App\Enums\CartaoStatusEnum;
use App\Lib\Zaps;
use App\Lib\ChatGPT;
use App\Models\Cartao;
use App\Models\IntranetMensagem;
use App\Services\CartaoService;
use App\Services\IgpmService;
use App\Services\IntranetMensagemService;
use App\Services\IpcaService;
use App\Services\SelicService;

class CronjobController extends Controller
{

    public function __construct()
    {
        parent::__construct();

        $config = Config::get('cronjob');

        // if (!empty($config['allowed_ip']) && $config['allowed_ip'] != $_SERVER['REMOTE_ADDR']) {
        //     http_response_code(403);
        //     exit('Forbidden');
        // }
    }

    // public function sendWhatsapp()
    // {
    //     $zaps = new Zaps();
    //     $number = '17997033832';
    //     $text = "Esta é uma mensagem enviada via API do Zaps para teste!\nQuabra de linha *negrito* ~tachado~ _italico_  😃 🚀✨";
    //     $response = $zaps->sendText($number, $text);
    //     printa($response);
    // }

    /**
     * Rotina diária para vencimento automático de cartões.
     *
     * Regras:
     * - cartão com validade anterior a hoje vira VENCIDO
     * - não mexe em cartões já cancelados
     * - não reprocessa cartões já vencidos
     */
    public function vencerCartoes(): void
    {
        $hoje = date('Y-m-d');

        $cartoes = Cartao::whereNotNull('ct.validade')
            ->where('DATE(ct.validade)', '<', $hoje)
            ->whereNotIn('ct.status', [
                CartaoStatusEnum::VENCIDO->value,
                CartaoStatusEnum::CANCELADO->value,
            ])
            ->get();

        $total = 0;

        foreach ($cartoes as $cartao) {
            Cartao::updateBy((int) $cartao->id, [
                'status' => CartaoStatusEnum::VENCIDO->value,
                'updated_by' => null,
            ]);

            $total++;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'date' => $hoje,
            'updated' => $total,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Rotina diária para expiração de créditos vencidos.
     *
     * Varre movimentações de crédito com expira_em < hoje e saldo_disponivel > 0,
     * lança um DEBITO de EXPIRACAO e zera o saldo_disponivel de cada uma.
     */
    public function vencerCreditosExpirados(): void
    {
        $hoje    = date('Y-m-d');
        $total   = 0;
        $service = new CartaoService();

        $cartaoIds = DB::run(
            "SELECT DISTINCT id_cartao
               FROM movimentacao
              WHERE sentido = 'CREDITO'
                AND saldo_disponivel > 0
                AND expira_em IS NOT NULL
                AND expira_em < ?",
            [$hoje]
        );

        foreach ($cartaoIds as $row) {
            $cartao = Cartao::find((int) $row['id_cartao']);

            if (!$cartao) {
                continue;
            }

            $total += $service->expirarCreditosVencidos($cartao);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'date'    => $hoje,
            'expired' => $total,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Rotina diária para sincronizar o IPCA acumulado no ano com a API do IBGE.
     *
     * Upsert idempotente por (ano, mes) — execuções redundantes são inofensivas,
     * garantindo que o valor do mês entre no sistema assim que o IBGE publicar.
     */
    public function ipcaSincronizar(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $qtd = (new IpcaService())->sincronizar();
            echo json_encode([
                'success' => true,
                'date'    => date('Y-m-d H:i:s'),
                'meses_sincronizados' => $qtd,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'date'    => date('Y-m-d H:i:s'),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Rotina diária para sincronizar a SELIC acumulada no ano com a API do Banco Central (SGS).
     */
    public function selicSincronizar(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $qtd = (new SelicService())->sincronizar();
            echo json_encode([
                'success' => true,
                'date'    => date('Y-m-d H:i:s'),
                'meses_sincronizados' => $qtd,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'date'    => date('Y-m-d H:i:s'),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Rotina diária para sincronizar o IGP-M acumulado no ano com a API do Banco Central (SGS).
     */
    public function igpmSincronizar(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $qtd = (new IgpmService())->sincronizar();
            echo json_encode([
                'success' => true,
                'date'    => date('Y-m-d H:i:s'),
                'meses_sincronizados' => $qtd,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'date'    => date('Y-m-d H:i:s'),
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }







}
