<?php

namespace App\Services;

use App\Models\Ipca;
use Exception;

class IpcaService
{
    private const VARIAVEL_ACUMULADO_ANO = 69;
    private const VARIAVEL_VARIACAO_MENSAL = 63;
    private const SIDRA_URL = "https://apisidra.ibge.gov.br/values/t/1737/n1/all/v/" . self::VARIAVEL_VARIACAO_MENSAL . "," . self::VARIAVEL_ACUMULADO_ANO . "/p/all";

    public function sincronizar(): int
    {
        $registros = $this->buscarNaApi();
        foreach ($registros as $registro) {
            Ipca::upsert($registro['ano'], $registro['mes'], $registro['acumulado'], $registro['mensal']);
        }
        return count($registros);
    }

    private function buscarNaApi(): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::SIDRA_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $resposta = curl_exec($ch);
        $erro = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resposta === false || $erro) {
            throw new Exception("Falha ao conectar com a API do IBGE: {$erro}");
        }
        if ($httpCode !== 200) {
            throw new Exception("API do IBGE retornou HTTP {$httpCode}");
        }

        $dados = json_decode($resposta, true);
        if (!is_array($dados) || count($dados) < 2) {
            throw new Exception("Resposta inesperada da API do IBGE");
        }

        array_shift($dados); // cabeçalho

        $porCompetencia = [];
        foreach ($dados as $linha) {
            $competencia = $linha['D3C'] ?? null;
            $variavel = $linha['D2C'] ?? null;
            $valor = $linha['V'] ?? null;

            if (!$competencia || $valor === null || $valor === '...' || !is_numeric($valor)) {
                continue;
            }
            $porCompetencia[$competencia][(int) $variavel] = (float) $valor;
        }

        $registros = [];
        foreach ($porCompetencia as $competencia => $valores) {
            if (!isset($valores[self::VARIAVEL_ACUMULADO_ANO])) {
                continue;
            }
            $registros[] = [
                'ano' => (int) substr($competencia, 0, 4),
                'mes' => (int) substr($competencia, 4, 2),
                'acumulado' => $valores[self::VARIAVEL_ACUMULADO_ANO],
                'mensal' => $valores[self::VARIAVEL_VARIACAO_MENSAL] ?? null,
            ];
        }
        return $registros;
    }
}
