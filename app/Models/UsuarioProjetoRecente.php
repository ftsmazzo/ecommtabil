<?php

namespace App\Models;

class UsuarioProjetoRecente extends \App\Core\Model
{
    public static string $table = "usuario_projeto_recente";
    public static ?string $alias = "upr";

    public static function registrarVisualizacao(int $idUsuario, int $idProjeto): void
    {
        if ($idUsuario <= 0 || $idProjeto <= 0) {
            return;
        }

        try {
            $existente = static::where("upr.id_usuario", "=", $idUsuario)
                ->where("upr.id_projeto", "=", $idProjeto)
                ->first();

            $agora = date("Y-m-d H:i:s");

            if ($existente) {
                static::updateBy($existente->id, [
                    "visualizado_em" => $agora,
                ]);
                return;
            }

            static::create([
                "id_usuario" => $idUsuario,
                "id_projeto" => $idProjeto,
                "visualizado_em" => $agora,
            ]);
        } catch (\Throwable $e) {
            return;
        }
    }

    public static function recentesPorUsuario(int $idUsuario, int $limit = 10): array
    {
        if ($idUsuario <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 50));

        try {
            $rows = static::leftJoin("projeto as p", "upr.id_projeto", "=", "p.id")
                ->leftJoin("empresa as e", "p.id_empresa", "=", "e.id")
                ->select(
                    "upr.id",
                    "upr.id_projeto",
                    "upr.visualizado_em",
                    "p.nome as projeto_nome",
                    "e.razao as empresa_razao",
                    "e.nome as empresa_nome"
                )
                ->where("upr.id_usuario", "=", $idUsuario)
                ->where("p.trash", "=", 0)
                ->orderBy("upr.visualizado_em", "DESC")
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(static function ($row) {
            $empresa = trim((string) ($row->empresa_razao ?: $row->empresa_nome));
            $nomeProjeto = trim((string) ($row->projeto_nome ?? ""));

            return [
                "id" => (int) ($row->id_projeto ?? 0),
                "label" => $nomeProjeto !== "" ? $nomeProjeto : ($empresa !== "" ? $empresa : "Projeto"),
            ];
        }, $rows);
    }
}
