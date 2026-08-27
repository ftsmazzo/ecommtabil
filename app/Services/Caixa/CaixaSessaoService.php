<?php

namespace App\Services\Caixa;

use App\Core\DB;
use App\Models\CaixaMovimento;
use App\Models\CaixaSessao;

class CaixaSessaoService
{
    private OfxParser $parser;

    public function __construct(?OfxParser $parser = null)
    {
        $this->parser = $parser ?? new OfxParser();
    }

    /**
     * Cria sessão a partir do OFX e grava movimentos.
     *
     * @return array{sessao:CaixaSessao, total:int}
     */
    public function criarDeOfx(int $idProjeto, string $caminhoArquivo, string $nomeOriginal, string $nomeSalvo): array
    {
        $parsed = $this->parser->parseFile($caminhoArquivo);

        $ins = DB::table("caixa_sessao")->insert([
            "id_projeto"        => $idProjeto,
            "periodo_inicio"    => $parsed["periodo_inicio"],
            "periodo_fim"       => $parsed["periodo_fim"],
            "status"            => "em_conferencia",
            "banco_nome"        => $parsed["banco_nome"],
            "banco_id"          => $parsed["banco_id"],
            "agencia"           => $parsed["agencia"],
            "conta"             => $parsed["conta"],
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
            "id_sessao",
            "fitid",
            "data_posted",
            "tipo",
            "valor",
            "memo",
            "id_dre_conta",
            "confianca_conta",
            "motivo_conta",
            "status",
            "id_lancamento",
            "trash",
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
}
