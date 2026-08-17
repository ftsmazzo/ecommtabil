<?php

namespace App\Models;

use App\Core\Model;

class ProjetoMapeamentoColuna extends Model
{
    public static string $table = "projeto_mapeamento_coluna";
    public static ?string $alias = "pmc";

    public static function porProjeto(int $idProjeto, string $tipo, int $aba = 0): array
    {
        $rows = static::where("id_projeto", "=", $idProjeto)
            ->where("tipo_demonstrativo", "=", $tipo)
            ->where("aba", "=", $aba)
            ->orderBy("pmc.indice_coluna")
            ->get();

        $mapa = [];
        foreach ($rows as $row) {
            $mapa[(int) $row->indice_coluna] = $row->mapeamento;
        }
        return $mapa;
    }
}
