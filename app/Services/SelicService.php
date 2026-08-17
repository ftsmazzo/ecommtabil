<?php

namespace App\Services;

use App\Models\Selic;

class SelicService extends BacenSgsService
{
    private const CODIGO_SERIE_SGS = 4390; // Selic acumulada no mês

    protected function codigoSerie(): int
    {
        return self::CODIGO_SERIE_SGS;
    }

    protected function upsert(int $ano, int $mes, float $valorAcumulado, ?float $variacaoMensal): void
    {
        Selic::upsert($ano, $mes, $valorAcumulado, $variacaoMensal);
    }
}
