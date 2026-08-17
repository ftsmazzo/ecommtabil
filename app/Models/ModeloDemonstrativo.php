<?php

namespace App\Models;

use App\Core\Model;
use App\Core\DB;

class ModeloDemonstrativo extends Model
{
    public static string $table = "modelo_demonstrativo";
    public static ?string $alias = "md";
    protected static array $required = ["nome", "tipo_demonstrativo"];
    public static array $uppers = [];

    public static function applySoftDeleteScope($query)
    {
        return $query->where("md.trash", "=", 0);
    }

    public static function padraoPorTipo(string $tipoDemonstrativo): ?static
    {
        $row = DB::table("modelo_demonstrativo")
            ->where("tipo_demonstrativo", "=", $tipoDemonstrativo)
            ->where("is_padrao", "=", 1)
            ->where("trash", "=", 0)
            ->first();

        return $row ? static::fromArray($row) : null;
    }

    public static function porEmpresaETipo(int $idEmpresa, string $tipoDemonstrativo): ?static
    {
        $row = DB::table("modelo_demonstrativo")
            ->where("id_empresa", "=", $idEmpresa)
            ->where("tipo_demonstrativo", "=", $tipoDemonstrativo)
            ->where("trash", "=", 0)
            ->first();

        return $row ? static::fromArray($row) : null;
    }

    /**
     * Todos os modelos de um tipo, com o padrão primeiro e os demais por nome.
     * Inclui o nome da empresa vinculada (quando houver) via join.
     */
    public static function listarPorTipo(string $tipoDemonstrativo): array
    {
        $rows = DB::table("modelo_demonstrativo", "md")
            ->leftJoin("empresa AS e", "e.id", "=", "md.id_empresa")
            ->where("md.tipo_demonstrativo", "=", $tipoDemonstrativo)
            ->where("md.trash", "=", 0)
            ->orderBy("md.is_padrao", "DESC")
            ->orderBy("md.nome")
            ->select(["md.*", "e.razao AS empresa_nome"])
            ->get();

        return array_map(fn ($row) => static::fromArray($row), $rows);
    }

    /**
     * Cria o modelo padrão vazio de um tipo (usado no seed e na criação
     * automática ao cadastrar um novo Tipo de Demonstrativo).
     */
    public static function criarPadraoParaTipo(string $tipoDemonstrativo, string $nomeTipo, int $idUsuario): static
    {
        $result = static::create([
            "id_empresa"         => null,
            "tipo_demonstrativo" => $tipoDemonstrativo,
            "nome"               => "Padrão — {$nomeTipo}",
            "is_padrao"          => 1,
            "trash"              => 0,
            "created_by"         => $idUsuario,
        ]);

        return static::find($result->id);
    }
}
