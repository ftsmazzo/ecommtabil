<?php

namespace App\Services\Caixa;

use App\Core\DB;
use App\Models\DreConta;

class CaixaConferenciaService
{
    public function aprovar(int $idMovimento, int $idSessao): bool
    {
        $m = $this->movimentoDaSessao($idMovimento, $idSessao);
        if (!$m || empty($m->id_dre_conta)) {
            return false;
        }
        DB::table("caixa_movimento")
            ->where("id", "=", $idMovimento)
            ->update(["status" => "aprovado"]);
        $this->aprovarVinculos($idMovimento);
        return true;
    }

    public function ignorar(int $idMovimento, int $idSessao): bool
    {
        $m = $this->movimentoDaSessao($idMovimento, $idSessao);
        if (!$m) {
            return false;
        }
        DB::table("caixa_movimento")
            ->where("id", "=", $idMovimento)
            ->update(["status" => "ignorado"]);
        return true;
    }

    public function editar(
        int $idMovimento,
        int $idSessao,
        int $idConta,
        bool $aprovar = true,
        ?string $grupoForcado = null
    ): bool {
        $m = $this->movimentoDaSessao($idMovimento, $idSessao);
        if (!$m) {
            return false;
        }
        $conta = DreConta::find($idConta);
        if (!$conta || strtolower((string) ($conta->tipo_demonstrativo ?? "")) !== "dfc") {
            return false;
        }
        if ((string) ($conta->tipo ?? "") !== "analitica") {
            return false;
        }

        $grupo = $grupoForcado;
        if ($grupo !== null && $grupo !== "") {
            $grupo = strtolower(trim($grupo));
            if (!in_array($grupo, ["operacional", "investimento", "financiamento"], true)) {
                $grupo = null;
            }
        }
        if ($grupo === null || $grupo === "") {
            $grupo = DfcGrupoResolver::grupoDaConta($idConta);
        }

        DB::table("caixa_movimento")
            ->where("id", "=", $idMovimento)
            ->update([
                "id_dre_conta"    => $idConta,
                "confianca_conta" => 100,
                "motivo_conta"    => "Ajuste manual",
                "grupo_dfc"       => $grupo,
                "status"          => $aprovar ? "aprovado" : "editado",
            ]);
        if ($aprovar) {
            $this->aprovarVinculos($idMovimento);
        }
        return true;
    }

    /**
     * Aprova todos os movimentos sugeridos/editados que já têm conta.
     */
    public function aprovarSugeridos(int $idSessao): int
    {
        $rows = DB::table("caixa_movimento")
            ->where("id_sessao", "=", $idSessao)
            ->where("trash", "=", 0)
            ->whereIn("status", ["sugerido", "editado", "novo"])
            ->whereNotNull("id_dre_conta")
            ->get();

        $n = 0;
        foreach ($rows as $r) {
            DB::table("caixa_movimento")
                ->where("id", "=", (int) $r->id)
                ->update(["status" => "aprovado"]);
            $this->aprovarVinculos((int) $r->id);
            $n++;
        }
        return $n;
    }

    /**
     * @return int quantidade aprovada
     */
    public function aprovarAltas(int $idSessao, int $minConf = 85): int
    {
        $rows = DB::table("caixa_movimento")
            ->where("id_sessao", "=", $idSessao)
            ->where("trash", "=", 0)
            ->whereIn("status", ["novo", "sugerido", "editado"])
            ->where("confianca_conta", ">=", $minConf)
            ->whereNotNull("id_dre_conta")
            ->get();

        $n = 0;
        foreach ($rows as $r) {
            DB::table("caixa_movimento")
                ->where("id", "=", (int) $r->id)
                ->update(["status" => "aprovado"]);
            $this->aprovarVinculos((int) $r->id);
            $n++;
        }
        return $n;
    }

    private function aprovarVinculos(int $idMovimento): void
    {
        DB::table("caixa_vinculo")
            ->where("id_movimento", "=", $idMovimento)
            ->where("trash", "=", 0)
            ->where("status", "=", "sugerido")
            ->update(["status" => "aprovado"]);
    }

    private function movimentoDaSessao(int $idMov, int $idSessao): ?object
    {
        return DB::table("caixa_movimento")
            ->where("id", "=", $idMov)
            ->where("id_sessao", "=", $idSessao)
            ->where("trash", "=", 0)
            ->first();
    }
}
