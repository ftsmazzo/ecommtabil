<?php

namespace App\Services\Caixa;

use App\Core\DB;
use App\Models\CaixaSessao;

class CaixaSessaoService
{
    private OfxParser $ofxParser;
    private ExtratoPdfParser $pdfParser;

    public function __construct(?OfxParser $ofxParser = null, ?ExtratoPdfParser $pdfParser = null)
    {
        $this->ofxParser = $ofxParser ?? new OfxParser();
        $this->pdfParser = $pdfParser ?? new ExtratoPdfParser();
    }

    /**
     * Detecta OFX ou PDF de extrato e cria sessão + movimentos.
     *
     * @return array{sessao:CaixaSessao, total:int, formato:string}
     */
    public function criarDeArquivo(int $idProjeto, string $caminhoArquivo, string $nomeOriginal, string $nomeSalvo): array
    {
        $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        if ($ext === "pdf" || strtolower(pathinfo($caminhoArquivo, PATHINFO_EXTENSION)) === "pdf") {
            $parsed = $this->pdfParser->parseFile($caminhoArquivo, $nomeOriginal);
            $formato = "pdf";
        } else {
            $parsed = $this->ofxParser->parseFile($caminhoArquivo);
            $formato = "ofx";
        }

        return array_merge(
            $this->persistirParsed($idProjeto, $parsed, $nomeOriginal, $nomeSalvo),
            ["formato" => $formato]
        );
    }

    /** @deprecated use criarDeArquivo */
    public function criarDeOfx(int $idProjeto, string $caminhoArquivo, string $nomeOriginal, string $nomeSalvo): array
    {
        $r = $this->criarDeArquivo($idProjeto, $caminhoArquivo, $nomeOriginal, $nomeSalvo);
        unset($r["formato"]);
        return $r;
    }

    /**
     * @param array<string,mixed> $parsed
     * @return array{sessao:CaixaSessao, total:int}
     */
    private function persistirParsed(int $idProjeto, array $parsed, string $nomeOriginal, string $nomeSalvo): array
    {
        $ins = DB::table("caixa_sessao")->insert([
            "id_projeto"        => $idProjeto,
            "periodo_inicio"    => $parsed["periodo_inicio"],
            "periodo_fim"       => $parsed["periodo_fim"],
            "status"            => "em_conferencia",
            "banco_nome"        => $parsed["banco_nome"] ?? null,
            "banco_id"          => $parsed["banco_id"] ?? null,
            "agencia"           => $parsed["agencia"] ?? null,
            "conta"             => $parsed["conta"] ?? null,
            "arquivo_extrato"   => $nomeSalvo,
            "arquivo_original"  => $nomeOriginal,
            "total_movimentos"  => 0,
            "trash"             => 0,
        ]);

        $idSessao = (int) ($ins->id ?? 0);
        if ($idSessao < 1) {
            throw new \RuntimeException("Falha ao criar a sessão de caixa.");
        }

        $cols = [
            "id_sessao", "fitid", "data_posted", "tipo", "valor", "memo",
            "id_dre_conta", "confianca_conta", "motivo_conta", "grupo_dfc",
            "status", "id_lancamento", "trash",
        ];

        $rows = [];
        $vistos = [];
        foreach ($parsed["movimentos"] as $m) {
            $fit = (string) $m["fitid"];
            if (isset($vistos[$fit])) {
                continue;
            }
            $vistos[$fit] = true;
            $rows[] = [
                (int) $idSessao,
                $fit,
                $m["data_posted"],
                $m["tipo"],
                $m["valor"],
                $m["memo"],
                null,
                0,
                null,
                null,
                "novo",
                null,
                0,
            ];
        }

        $total = DB::table("caixa_movimento")->insertMany($cols, $rows, 400, true);

        DB::table("caixa_sessao")
            ->where("id", "=", (int) $idSessao)
            ->update(["total_movimentos" => $total]);

        $sessao = CaixaSessao::findAtiva((int) $idSessao, $idProjeto);
        if (!$sessao) {
            throw new \RuntimeException("Sessão criada, mas não encontrada.");
        }

        return ["sessao" => $sessao, "total" => $total];
    }

    public function arquivar(int $idSessao, int $idProjeto): void
    {
        DB::table("caixa_sessao")
            ->where("id", "=", $idSessao)
            ->where("id_projeto", "=", $idProjeto)
            ->update(["trash" => 1, "status" => "cancelada"]);
    }

    /**
     * Apaga de verdade todas as montagens do projeto (sessões, movimentos, recibos e vínculos).
     */
    public function zerarProjeto(int $idProjeto): int
    {
        $ids = DB::table("caixa_sessao")
            ->select(["id"])
            ->where("id_projeto", "=", $idProjeto)
            ->get();

        $sessaoIds = [];
        foreach ($ids as $row) {
            $sessaoIds[] = (int) $row->id;
        }
        if ($sessaoIds === []) {
            return 0;
        }

        $movIds = [];
        $movs = DB::table("caixa_movimento")
            ->select(["id"])
            ->whereIn("id_sessao", $sessaoIds)
            ->get();
        foreach ($movs as $m) {
            $movIds[] = (int) $m->id;
        }

        if ($movIds !== []) {
            DB::table("caixa_vinculo")->whereIn("id_movimento", $movIds)->delete();
        }

        $recIds = [];
        $recs = DB::table("caixa_recibo")
            ->select(["id"])
            ->whereIn("id_sessao", $sessaoIds)
            ->get();
        foreach ($recs as $r) {
            $recIds[] = (int) $r->id;
        }
        if ($recIds !== []) {
            DB::table("caixa_vinculo")->whereIn("id_recibo", $recIds)->delete();
        }

        DB::table("caixa_recibo")->whereIn("id_sessao", $sessaoIds)->delete();
        DB::table("caixa_movimento")->whereIn("id_sessao", $sessaoIds)->delete();
        DB::table("caixa_sessao")->where("id_projeto", "=", $idProjeto)->delete();

        return count($sessaoIds);
    }
}
