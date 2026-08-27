<?php

namespace App\Services\Caixa;

use App\Core\DB;
use App\Models\ProjetoLancamento;

/**
 * Fecha a montagem: movimentos aprovados → projeto_lancamento (tipo dfc).
 */
class CaixaGerarDfcService
{
    /** Aba reservada para lançamentos vindos do Montar DFC (não mistura com importação de planilha). */
    public const ABA_MONTAR = 99;

    /**
     * @return array{gravados:int,pulados:int,valor_total:float}
     */
    public function gerar(int $idSessao, int $idProjeto, int $idUsuario = 0): array
    {
        $sessao = DB::table("caixa_sessao")
            ->where("id", "=", $idSessao)
            ->where("id_projeto", "=", $idProjeto)
            ->where("trash", "=", 0)
            ->first();
        if (!$sessao) {
            throw new \RuntimeException("Sessão de caixa não encontrada.");
        }

        $aprovados = DB::table("caixa_movimento")
            ->where("id_sessao", "=", $idSessao)
            ->where("trash", "=", 0)
            ->where("status", "=", "aprovado")
            ->whereNotNull("id_dre_conta")
            ->orderBy("data_posted")
            ->orderBy("id")
            ->get();

        if ($aprovados === []) {
            throw new \RuntimeException("Não há lançamentos aprovados com conta DFC para gerar.");
        }

        // Remove geração anterior desta origem (mesma aba) para poder re-gerar
        ProjetoLancamento::limparPorProjeto($idProjeto, "dfc", self::ABA_MONTAR);

        // Também limpa vínculos id_lancamento desta sessão
        DB::table("caixa_movimento")
            ->where("id_sessao", "=", $idSessao)
            ->update(["id_lancamento" => null]);

        $gravados = 0;
        $pulados = 0;
        $valorTotal = 0.0;
        $linha = 0;

        foreach ($aprovados as $m) {
            $idConta = (int) ($m->id_dre_conta ?? 0);
            if ($idConta < 1) {
                $pulados++;
                continue;
            }

            $linha++;
            $periodo = null;
            if (!empty($m->data_posted)) {
                $periodo = substr((string) $m->data_posted, 0, 7) . "-01";
            }

            $valor = (float) $m->valor;
            $desc = trim((string) ($m->memo ?? ""));
            if ($desc === "") {
                $desc = "Movimento " . (string) ($m->fitid ?? $m->id);
            }

            $ins = ProjetoLancamento::create([
                "id_projeto"         => $idProjeto,
                "tipo_demonstrativo" => "dfc",
                "aba"                => self::ABA_MONTAR,
                "id_dre_conta"       => $idConta,
                "periodo"            => $periodo,
                "descricao"          => mb_substr($desc, 0, 500),
                "valor"              => $valor,
                "unidade"            => null,
                "linha"              => $linha,
                "mapeamento"         => "montar-dfc",
                "created_by"         => $idUsuario ?: null,
            ]);

            $idLanc = (int) ($ins->id ?? 0);
            if ($idLanc > 0) {
                DB::table("caixa_movimento")
                    ->where("id", "=", (int) $m->id)
                    ->update(["id_lancamento" => $idLanc]);
                $gravados++;
                $valorTotal += $valor;
            } else {
                $pulados++;
            }
        }

        DB::table("caixa_sessao")
            ->where("id", "=", $idSessao)
            ->update(["status" => "finalizada"]);

        return [
            "gravados"    => $gravados,
            "pulados"     => $pulados,
            "valor_total" => round($valorTotal, 2),
        ];
    }
}
