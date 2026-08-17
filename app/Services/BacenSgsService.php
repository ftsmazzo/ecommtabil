<?php

namespace App\Services;

use Exception;

abstract class BacenSgsService
{
    private const SGS_URL = "https://api.bcb.gov.br/dados/serie/bcdata.sgs.%d/dados?formato=json";

    abstract protected function codigoSerie(): int;

    abstract protected function upsert(int $ano, int $mes, float $valorAcumulado, ?float $variacaoMensal): void;

    public function sincronizar(): int
    {
        $registros = $this->buscarNaApi();
        foreach ($registros as $registro) {
            $this->upsert($registro['ano'], $registro['mes'], $registro['acumulado'], $registro['mensal']);
        }
        return count($registros);
    }

    private function buscarNaApi(): array
    {
        $url = sprintf(self::SGS_URL, $this->codigoSerie());

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $resposta = curl_exec($ch);
        $erro = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resposta === false || $erro) {
            throw new Exception("Falha ao conectar com a API do Banco Central: {$erro}");
        }
        if ($httpCode !== 200) {
            throw new Exception("API do Banco Central retornou HTTP {$httpCode}");
        }

        $dados = json_decode($resposta, true);
        if (!is_array($dados) || empty($dados)) {
            throw new Exception("Resposta inesperada da API do Banco Central");
        }

        $porMes = [];
        foreach ($dados as $linha) {
            $data = $linha['data'] ?? null;
            $valor = $linha['valor'] ?? null;

            if (!$data || $valor === null || !is_numeric($valor)) {
                continue;
            }

            [$dia, $mes, $ano] = explode('/', $data);
            $porMes[(int) $ano][(int) $mes] = (float) $valor;
        }

        ksort($porMes);

        $registros = [];
        foreach ($porMes as $ano => $meses) {
            ksort($meses);
            $fatorAcumulado = 1.0;
            foreach ($meses as $mes => $variacaoMensal) {
                $fatorAcumulado *= (1 + $variacaoMensal / 100);
                $registros[] = [
                    'ano' => $ano,
                    'mes' => $mes,
                    'acumulado' => round(($fatorAcumulado - 1) * 100, 4),
                    'mensal' => $variacaoMensal,
                ];
            }
        }

        return $registros;
    }
}
