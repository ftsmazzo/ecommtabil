<?php

namespace App\Services\Importacao;

use App\Core\DB;
use App\Models\DreConta;
use Throwable;

/**
 * Guarda o de-para por fingerprint de cabeçalhos + tipo de demonstrativo.
 * Usa nome da conta (não id), para o pacote funcionar no FTP e em outro banco.
 */
class OrigemPerfilService
{
    /**
     * @param array<int,string> $headers
     * @param array<int,object> $contas
     * @return array{campos: array<string,int>, periodos_matriz: array<int,int>}|null
     */
    public function buscar(string $fingerprint, string $tipo, array $contas): ?array
    {
        try {
            $row = DB::table("projeto_origem_perfil")
                ->where("fingerprint", "=", $fingerprint)
                ->where("tipo_demonstrativo", "=", $tipo)
                ->first();
        } catch (Throwable $e) {
            return null;
        }
        if (!$row || empty($row->mapeamento_json)) {
            return null;
        }
        $json = json_decode((string) $row->mapeamento_json, true);
        if (!is_array($json)) {
            return null;
        }
        return $this->hidratar($json, $contas);
    }

    /**
     * @param array<string,int> $campos
     * @param array<int,int> $periodos
     * @param array<int,object> $contas
     */
    public function gravar(
        string $fingerprint,
        string $tipo,
        string $familia,
        array $campos,
        array $periodos,
        array $contas
    ): void {
        $porId = [];
        foreach ($contas as $c) {
            $porId[(int) $c->id] = trim((string) $c->nome);
        }
        $canonico = [];
        foreach ($campos as $dest => $indice) {
            $dest = (string) $dest;
            $indice = (int) $indice;
            if (str_starts_with($dest, "conta_")) {
                $id = (int) substr($dest, 6);
                $nome = $porId[$id] ?? "";
                if ($nome === "") {
                    continue;
                }
                $canonico["conta:" . $nome] = $indice;
                continue;
            }
            $canonico[$dest] = $indice;
        }
        $payload = json_encode([
            "familia"         => $familia,
            "campos"          => $canonico,
            "periodos_matriz" => array_values(array_map("intval", $periodos)),
        ], JSON_UNESCAPED_UNICODE);

        try {
            $existente = DB::table("projeto_origem_perfil")
                ->where("fingerprint", "=", $fingerprint)
                ->where("tipo_demonstrativo", "=", $tipo)
                ->first();
            if ($existente) {
                DB::table("projeto_origem_perfil")
                    ->where("id", "=", (int) $existente->id)
                    ->update([
                        "familia"          => $familia,
                        "mapeamento_json"  => $payload,
                    ]);
                return;
            }
            DB::table("projeto_origem_perfil")->insert([
                "fingerprint"         => $fingerprint,
                "tipo_demonstrativo"  => $tipo,
                "familia"             => $familia,
                "mapeamento_json"     => $payload,
            ]);
        } catch (Throwable $e) {
            // migration ainda não aplicada neste ambiente
        }
    }

    /**
     * @param array<string,mixed> $json
     * @param array<int,object> $contas
     * @return array{campos: array<string,int>, periodos_matriz: array<int,int>}
     */
    private function hidratar(array $json, array $contas): array
    {
        $campos = [];
        foreach ((array) ($json["campos"] ?? []) as $chave => $indice) {
            $chave = (string) $chave;
            $indice = (int) $indice;
            if ($indice < 0) {
                continue;
            }
            if (str_starts_with($chave, "conta:")) {
                $nome = substr($chave, 6);
                $conta = DreConta::casarEmLista($contas, $nome);
                if ($conta) {
                    $campos["conta_" . (int) $conta->id] = $indice;
                }
                continue;
            }
            $campos[$chave] = $indice;
        }
        $periodos = array_map("intval", (array) ($json["periodos_matriz"] ?? []));
        return ["campos" => $campos, "periodos_matriz" => $periodos];
    }
}
