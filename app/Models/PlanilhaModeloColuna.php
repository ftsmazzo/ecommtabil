<?php

namespace App\Models;

use App\Core\Model;

class PlanilhaModeloColuna extends Model
{
    public static string $table = "planilha_modelo_coluna";
    public static ?string $alias = "pmc";
    protected static array $required = ["descricao", "id_modelo_demonstrativo"];
    public static array $uppers = [];

    public static function applySoftDeleteScope($query)
    {
        return $query->where("pmc.trash", "=", 0);
    }

    public static function porModelo(int $idModeloDemonstrativo): array
    {
        return self::query()
            ->where("pmc.id_modelo_demonstrativo", "=", $idModeloDemonstrativo)
            ->orderBy("pmc.ordem")
            ->orderBy("pmc.id")
            ->get();
    }

    /**
     * Completa campo_dre nas linhas já cadastradas e, se não houver nenhuma,
     * cria Data / Descrição / Valor (o mínimo do de-para).
     */
    public static function garantirPadrao(int $idModeloDemonstrativo, int $idUsuario = 0): void
    {
        $alias = [
            "descricao"  => \App\Services\Importacao\PlanilhaImportacaoService::DEST_DESCRICAO,
            "historico"  => \App\Services\Importacao\PlanilhaImportacaoService::DEST_DESCRICAO,
            "valor"      => \App\Services\Importacao\PlanilhaImportacaoService::DEST_VALOR,
            "total"      => \App\Services\Importacao\PlanilhaImportacaoService::DEST_VALOR,
            "data"       => \App\Services\Importacao\PlanilhaImportacaoService::DEST_PERIODO,
            "periodo"    => \App\Services\Importacao\PlanilhaImportacaoService::DEST_PERIODO,
            "competencia"=> \App\Services\Importacao\PlanilhaImportacaoService::DEST_PERIODO,
            "unidade"    => \App\Services\Importacao\PlanilhaImportacaoService::DEST_UNIDADE,
            "conta"      => \App\Services\Importacao\PlanilhaImportacaoService::DEST_CONTA,
        ];

        $norm = static function (string $t): string {
            $t = mb_strtolower(trim($t), "UTF-8");
            $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $t);
            $t = is_string($ascii) ? $ascii : $t;
            return (string) preg_replace("/[^a-z0-9]+/", "", $t);
        };

        $cols = self::porModelo($idModeloDemonstrativo);
        foreach ($cols as $col) {
            if (trim((string) ($col->campo_dre ?? "")) !== "") {
                continue;
            }
            $chave = $norm((string) $col->descricao);
            if (!isset($alias[$chave])) {
                continue;
            }
            self::updateBy($col->id, ["campo_dre" => $alias[$chave]]);
        }

        $cols = self::porModelo($idModeloDemonstrativo);
        if ($cols !== []) {
            return;
        }

        $seed = [
            ["Data", "Ex.: data da venda ou competência", \App\Services\Importacao\PlanilhaImportacaoService::DEST_PERIODO],
            ["Descrição", "Ex.: produto, histórico", \App\Services\Importacao\PlanilhaImportacaoService::DEST_DESCRICAO],
            ["Valor", "Ex.: total, receita, valor", \App\Services\Importacao\PlanilhaImportacaoService::DEST_VALOR],
        ];
        $letras = range("A", "Z");
        foreach ($seed as $i => [$desc, $helper, $destino]) {
            self::create([
                "id_modelo_demonstrativo" => $idModeloDemonstrativo,
                "ordem"                   => $i,
                "coluna"                  => $letras[$i],
                "descricao"               => $desc,
                "helper"                  => $helper,
                "campo_dre"               => $destino,
                "trash"                   => 0,
                "created_by"              => $idUsuario ?: null,
            ]);
        }
    }
}
