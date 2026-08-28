<?php

namespace App\Models;

use App\Core\Model;
use App\Core\DB;

class TipoDemonstrativo extends Model
{
    public static string $table = "tipo_demonstrativo";
    public static ?string $alias = "td";
    protected static array $required = ["nome", "sigla"];
    public static array $uppers = [];

    public static function applySoftDeleteScope($query)
    {
        return $query->where("td.trash", "=", 0);
    }

    public static function listar(): array
    {
        $rows = DB::table("tipo_demonstrativo", "td")
            ->where("td.trash", "=", 0)
            ->orderBy("td.ordem")
            ->orderBy("td.nome")
            ->get();

        return array_map(fn ($row) => static::fromArray($row), $rows);
    }

    /**
     * Retorna [sigla => "SIGLA — nome"], ordenado por ordem.
     */
    public static function options(): array
    {
        $options = [];
        foreach (static::listar() as $tipo) {
            $options[$tipo->sigla] = $tipo->sigla . " — " . $tipo->nome;
        }
        return $options;
    }

    public static function padrao(): ?static
    {
        $rows = static::listar();
        return $rows[0] ?? null;
    }

    public static function existeSigla(string $sigla): bool
    {
        return DB::table("tipo_demonstrativo")
            ->where("sigla", "=", $sigla)
            ->where("trash", "=", 0)
            ->count() > 0;
    }

    public static function porSigla(string $sigla): ?static
    {
        return static::firstWhere("sigla", $sigla);
    }

    /** Rota da tela dedicada ao demonstrativo (DRE / DFC / BP). */
    public static function routeName(string $sigla): string
    {
        return match (strtoupper(trim($sigla))) {
            "DFC"   => "admin.projeto.dfc",
            "BP"    => "admin.projeto.bp",
            default => "admin.projeto.dre",
        };
    }

    /** Identificador da aba no menu do projeto. */
    public static function abaNav(string $sigla): string
    {
        return match (strtoupper(trim($sigla))) {
            "DFC"   => "dfc",
            "BP"    => "bp",
            default => "dre",
        };
    }

    public static function tituloTela(string $sigla): string
    {
        return match (strtoupper(trim($sigla))) {
            "DFC"   => "Fluxo de Caixa (DFC)",
            "BP"    => "Balanço Patrimonial",
            default => "Demonstrativo de Resultado (DRE)",
        };
    }
}
