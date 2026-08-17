<?php

namespace App\Models;

use App\Core\Model;
use App\Core\DB;

class Ipca extends Model
{
    public static string $table = "ipca_mensal";

    public static function listaOrdenada(): array
    {
        return self::orderBy("ano", "desc")->orderBy("mes", "desc")->get();
    }

    public static function upsert(int $ano, int $mes, float $valorAcumulado, ?float $variacaoMensal = null): void
    {
        DB::execute(
            "INSERT INTO ipca_mensal (ano, mes, valor_acumulado, variacao_mensal)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE valor_acumulado = VALUES(valor_acumulado), variacao_mensal = VALUES(variacao_mensal)",
            [$ano, $mes, $valorAcumulado, $variacaoMensal]
        );
    }

    public static function mapaPorAno(int $ano): array
    {
        $rows = self::where("ano", "=", $ano)->orderBy("mes")->get();
        $mapa = [];
        foreach ($rows as $row) {
            $mapa[(int) $row->mes] = (float) $row->valor_acumulado;
        }
        return $mapa;
    }

    public static function ultimoRegistro(): ?object
    {
        $rows = self::orderBy("ano", "desc")->orderBy("mes", "desc")->limit(1)->get();
        return $rows[0] ?? null;
    }
}
