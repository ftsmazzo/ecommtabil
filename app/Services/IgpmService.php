<?php

namespace App\Services;

use App\Models\Igpm;

class IgpmService extends BacenSgsService
{
    private const CODIGO_SERIE_SGS = 189; // IGP-M variação mensal (FGV)

    protected function codigoSerie(): int
    {
        return self::CODIGO_SERIE_SGS;
    }

    protected function upsert(int $ano, int $mes, float $valorAcumulado, ?float $variacaoMensal): void
    {
        Igpm::upsert($ano, $mes, $valorAcumulado, $variacaoMensal);
    }
}
