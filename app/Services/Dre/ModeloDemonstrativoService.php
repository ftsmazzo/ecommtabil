<?php

namespace App\Services\Dre;

use App\Core\DB;
use App\Models\ModeloDemonstrativoNo;
use InvalidArgumentException;

class ModeloDemonstrativoService
{
    /**
     * Valida a árvore recebida do client (array associativo já decodificado do JSON).
     * Lança InvalidArgumentException com mensagem amigável em caso de estrutura inválida.
     *
     * @param array $arvore   Lista de nós raiz, cada um: ["tipo","nome","id_conta"?,"filhos"?]
     * @param array $idsContasValidas  ids de dre_conta (sintetica ou analitica, trash=0) permitidos
     */
    public static function validarArvore(array $arvore, array $idsContasValidas): array
    {
        $idsValidos = array_flip($idsContasValidas);

        $validar = function (array $nos, callable $validar) use ($idsValidos): array {
            $resultado = [];

            foreach ($nos as $no) {
                $tipo = $no["tipo"] ?? "";
                if (!in_array($tipo, ModeloDemonstrativoNo::TIPOS, true)) {
                    throw new InvalidArgumentException("Tipo de nó inválido: {$tipo}");
                }

                $nome = trim((string) ($no["nome"] ?? ""));
                if ($nome === "") {
                    throw new InvalidArgumentException("Todo item da estrutura precisa de um nome.");
                }

                $filhos   = $no["filhos"] ?? [];
                $idConta  = $no["id_conta"] ?? null;

                if ($tipo === "conta") {
                    if (!empty($filhos)) {
                        throw new InvalidArgumentException("O item \"{$nome}\" vincula uma conta e não pode ter subitens.");
                    }
                    if ($idConta === null || $idConta === "") {
                        throw new InvalidArgumentException("Selecione uma conta do Plano de Contas para o item \"{$nome}\".");
                    }
                    $idConta = (int) $idConta;
                    if (!isset($idsValidos[$idConta])) {
                        throw new InvalidArgumentException("Conta inválida vinculada ao item \"{$nome}\".");
                    }
                    $resultado[] = [
                        "tipo"     => "conta",
                        "nome"     => $nome,
                        "id_conta" => $idConta,
                        "filhos"   => [],
                    ];
                    continue;
                }

                if ($tipo === "totalizador") {
                    if (!empty($filhos) || $idConta) {
                        throw new InvalidArgumentException("O totalizador \"{$nome}\" não pode ter subitens ou conta vinculada.");
                    }
                    $resultado[] = ["tipo" => "totalizador", "nome" => $nome, "id_conta" => null, "filhos" => []];
                    continue;
                }

                // organizador
                if ($idConta) {
                    throw new InvalidArgumentException("O item \"{$nome}\" agrupa subitens e não pode vincular uma conta.");
                }
                $resultado[] = [
                    "tipo"     => "organizador",
                    "nome"     => $nome,
                    "id_conta" => null,
                    "filhos"   => $validar((array) $filhos, $validar),
                ];
            }

            return $resultado;
        };

        return $validar($arvore, $validar);
    }

    /**
     * Apaga todos os nós do modelo e recria a árvore inteira a partir
     * da estrutura já validada por validarArvore().
     */
    public static function substituirArvore(int $idModelo, array $arvoreValidada, int $idUsuario): void
    {
        DB::transaction(function () use ($idModelo, $arvoreValidada, $idUsuario) {
            $idsAtuais = DB::table("modelo_demonstrativo_no")
                ->where("id_configuracao", "=", $idModelo)
                ->select(["id"])
                ->get();
            $idsAtuais = array_map(fn ($r) => $r->id, $idsAtuais);

            if (!empty($idsAtuais)) {
                DB::table("modelo_demonstrativo_no_conta")
                    ->whereIn("id_no", $idsAtuais)
                    ->delete();
            }

            DB::table("modelo_demonstrativo_no")
                ->where("id_configuracao", "=", $idModelo)
                ->delete();

            self::inserirNos($idModelo, null, $arvoreValidada, $idUsuario);
        });
    }

    private static function inserirNos(int $idModelo, ?int $idPai, array $nos, int $idUsuario): void
    {
        foreach ($nos as $ordem => $no) {
            $result = DB::table("modelo_demonstrativo_no")->insert([
                "id_configuracao" => $idModelo,
                "id_pai"          => $idPai,
                "tipo"            => $no["tipo"],
                "nome"            => $no["nome"],
                "ordem"           => $ordem,
                "trash"           => 0,
                "created_by"      => $idUsuario,
            ]);

            $idNo = $result->id;

            if ($no["tipo"] === "conta" && !empty($no["id_conta"])) {
                DB::table("modelo_demonstrativo_no_conta")->insert([
                    "id_no"    => $idNo,
                    "id_conta" => $no["id_conta"],
                ]);
            }

            if (!empty($no["filhos"])) {
                self::inserirNos($idModelo, $idNo, $no["filhos"], $idUsuario);
            }
        }
    }

    /**
     * Clona a árvore de um modelo origem para um modelo destino (já criado
     * e vazio, do mesmo tipo de demonstrativo). ids de conta são globais e
     * não precisam de remapeamento.
     */
    public static function clonarArvore(int $idModeloOrigem, int $idModeloDestino, int $idUsuario): void
    {
        DB::transaction(function () use ($idModeloOrigem, $idModeloDestino, $idUsuario) {
            $arvore = ModeloDemonstrativoNo::arvorePorConfiguracao($idModeloOrigem);

            $clonar = function (?int $idPaiOrigem, ?int $idPaiDestino) use (&$clonar, $arvore, $idModeloDestino, $idUsuario): void {
                $filhos = $arvore[$idPaiOrigem ?? 0] ?? [];

                foreach ($filhos as $ordem => $no) {
                    $result = DB::table("modelo_demonstrativo_no")->insert([
                        "id_configuracao" => $idModeloDestino,
                        "id_pai"          => $idPaiDestino,
                        "tipo"            => $no->tipo,
                        "nome"            => $no->nome,
                        "ordem"           => $ordem,
                        "trash"           => 0,
                        "created_by"      => $idUsuario,
                    ]);

                    $idNovo = $result->id;

                    if ($no->tipo === "conta" && !empty($no->conta)) {
                        DB::table("modelo_demonstrativo_no_conta")->insert([
                            "id_no"    => $idNovo,
                            "id_conta" => $no->conta->id,
                        ]);
                    }

                    $clonar($no->id, $idNovo);
                }
            };

            $clonar(null, null);
        });
    }
}
