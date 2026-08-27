<?php

namespace App\Services\Caixa;

use App\Core\DB;
use App\Models\CaixaRecibo;

/**
 * Upload de recibos + cruzamento com movimentos (auto + manual).
 */
class CaixaReciboService
{
    public function __construct(
        private ComprovantePdfParser $comprovanteParser = new ComprovantePdfParser(),
        private PdfTextExtractor $extractor = new PdfTextExtractor()
    ) {
    }

    /**
     * @param array<int,array{name:string,tmp_name:string,error:int,size:int,type?:string}> $files
     * @return array{criados:int,vinculos:int,avisos:array<int,string>}
     */
    public function uploadLote(int $idSessao, array $files): array
    {
        $dir = PATH_ROOT . "/storage/tmp/caixa/recibos/";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $criados = 0;
        $avisos = [];
        $houveRecibo = false;

        foreach ($files as $file) {
            if (($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $nome = (string) ($file["name"] ?? "recibo");
            $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
            if (!in_array($ext, ["pdf", "png", "jpg", "jpeg", "webp"], true)) {
                $avisos[] = "Ignorado (formato): {$nome}";
                continue;
            }

            $salvo = "sessao_{$idSessao}_" . time() . "_" . uniqid() . "." . $ext;
            $destino = $dir . $salvo;
            if (!move_uploaded_file((string) $file["tmp_name"], $destino)) {
                $avisos[] = "Falha ao salvar: {$nome}";
                continue;
            }

            if ($ext === "pdf") {
                try {
                    $itens = $this->comprovanteParser->parseFile($destino, $nome);
                } catch (\Throwable $e) {
                    $itens = [[
                        "valor"         => null,
                        "data"          => null,
                        "contraparte"   => null,
                        "ident_extrato" => null,
                        "texto"         => $this->extractor->extract($destino, $nome),
                    ]];
                    $avisos[] = "PDF {$nome}: parse parcial — " . $e->getMessage();
                }

                if ($itens === []) {
                    $itens = [[
                        "valor" => null, "data" => null, "contraparte" => null,
                        "ident_extrato" => null, "texto" => $this->extractor->extract($destino, $nome),
                    ]];
                }

                $idx = 0;
                foreach ($itens as $item) {
                    $idx++;
                    $pathRel = "recibos/" . preg_replace('/\.pdf$/i', "_{$idx}.pdf", $salvo);
                    if ($this->inserirRecibo($idSessao, $pathRel, $nome . " #{$idx}", $item)) {
                        $criados++;
                        $houveRecibo = true;
                    }
                }
            } else {
                $extra = ["data" => null, "valor" => null, "contraparte" => null, "ident_extrato" => null, "texto" => ""];
                if ($this->inserirRecibo($idSessao, "recibos/" . $salvo, $nome, $extra)) {
                    $criados++;
                    $houveRecibo = true;
                }
            }
        }

        $vinculos = 0;
        if ($houveRecibo) {
            $vinculos = $this->cruzarSessao($idSessao);
        }

        return ["criados" => $criados, "vinculos" => $vinculos, "avisos" => $avisos];
    }

    /**
     * @param array{valor:?float,data:?string,contraparte:?string,ident_extrato:?string,texto:string} $item
     */
    private function inserirRecibo(int $idSessao, string $pathRel, string $nomeOriginal, array $item): bool
    {
        $texto = (string) ($item["texto"] ?? "");
        if ($texto === "" && $item["valor"] === null) {
            return false;
        }

        $ins = DB::table("caixa_recibo")->insert([
            "id_sessao"       => $idSessao,
            "arquivo_path"    => $pathRel,
            "nome_original"   => mb_substr($nomeOriginal, 0, 255),
            "data_doc"        => $item["data"] ?? null,
            "valor"           => $item["valor"] ?? null,
            "texto_extraido"  => $texto !== "" ? mb_substr($texto, 0, 50000) : null,
            "contraparte"     => $item["contraparte"] ?? null,
            "ident_extrato"   => $item["ident_extrato"] ?? null,
            "status_extracao" => ($texto !== "" || $item["valor"] !== null) ? "ok" : "pendente",
            "trash"           => 0,
        ]);

        return (int) ($ins->id ?? 0) > 0;
    }

    public function cruzarSessao(int $idSessao): int
    {
        $recibos = CaixaRecibo::porSessao($idSessao);
        $movs = DB::table("caixa_movimento")
            ->where("id_sessao", "=", $idSessao)
            ->where("trash", "=", 0)
            ->whereNotIn("status", ["ignorado"])
            ->get();

        $ocupadosRecibo = $this->recibosJaVinculados($idSessao);
        $ocupadosMov1a1 = $this->movimentosComVinculoExclusivo($idSessao);

        $candidatos = [];
        foreach ($recibos as $rec) {
            $idRec = (int) $rec->id;
            if (isset($ocupadosRecibo[$idRec])) {
                continue; // já tem vínculo (manual ou auto) — não reprocessa
            }
            foreach ($movs as $m) {
                $score = $this->scoreMatch($m, $rec);
                if ($score < 45) {
                    continue;
                }
                $candidatos[] = [
                    "score" => $score,
                    "mov"   => $m,
                    "rec"   => $rec,
                ];
            }
        }

        usort($candidatos, static fn ($a, $b) => $b["score"] <=> $a["score"]);

        $criados = 0;
        $usadosRec = $ocupadosRecibo;
        $usadosMov = $ocupadosMov1a1;

        foreach ($candidatos as $c) {
            $idRec = (int) $c["rec"]->id;
            $idMov = (int) $c["mov"]->id;
            if (isset($usadosRec[$idRec])) {
                continue;
            }
            // 1:1: movimento com valor ≈ recibo não recebe outro 1:1
            $vRec = $c["rec"]->valor !== null ? abs((float) $c["rec"]->valor) : null;
            $vMov = abs((float) $c["mov"]->valor);
            $eh1a1 = $vRec !== null && abs($vMov - $vRec) <= 0.05;
            if ($eh1a1 && isset($usadosMov[$idMov])) {
                continue;
            }

            $motivo = $this->motivoScore($c["score"], $c["mov"], $c["rec"]);
            if ($this->salvarVinculo($idMov, $idRec, $c["score"], $c["mov"], "auto", "sugerido", $motivo)) {
                $criados++;
            }
            $usadosRec[$idRec] = true;
            if ($eh1a1) {
                $usadosMov[$idMov] = true;
            }
        }

        $criados += $this->cruzarAgregados($idSessao, $recibos, $movs, $usadosRec);

        return $criados;
    }

    /**
     * Vínculo manual (de-para): cria ou move o recibo para o movimento escolhido.
     */
    public function vincularManual(int $idSessao, int $idRecibo, int $idMovimento): bool
    {
        $rec = DB::table("caixa_recibo")
            ->where("id", "=", $idRecibo)
            ->where("id_sessao", "=", $idSessao)
            ->where("trash", "=", 0)
            ->first();
        $mov = DB::table("caixa_movimento")
            ->where("id", "=", $idMovimento)
            ->where("id_sessao", "=", $idSessao)
            ->where("trash", "=", 0)
            ->first();
        if (!$rec || !$mov) {
            return false;
        }

        // Remove vínculos anteriores deste recibo (troca de de-para)
        DB::table("caixa_vinculo")
            ->where("id_recibo", "=", $idRecibo)
            ->where("trash", "=", 0)
            ->update(["trash" => 1, "status" => "removido"]);

        return $this->salvarVinculo(
            $idMovimento,
            $idRecibo,
            100,
            $mov,
            "manual",
            "confirmado",
            "Vínculo manual (de-para)"
        );
    }

    public function desvincular(int $idSessao, int $idVinculo): bool
    {
        $row = DB::execute(
            "SELECT v.id
             FROM caixa_vinculo v
             INNER JOIN caixa_movimento m ON m.id = v.id_movimento AND m.id_sessao = ? AND m.trash = 0
             WHERE v.id = ? AND v.trash = 0
             LIMIT 1",
            [$idSessao, $idVinculo]
        );
        if ($row === []) {
            return false;
        }

        DB::table("caixa_vinculo")
            ->where("id", "=", $idVinculo)
            ->update(["trash" => 1, "status" => "removido"]);

        return true;
    }

    /**
     * Resumo de conferência: extrato × recibos × vínculos (para não perder nada).
     *
     * @return array<string,mixed>
     */
    public function conferencia(int $idSessao): array
    {
        $tot = DB::execute(
            "SELECT
                COUNT(*) AS qtd,
                COALESCE(SUM(CASE WHEN valor > 0 THEN valor ELSE 0 END), 0) AS creditos,
                COALESCE(SUM(CASE WHEN valor < 0 THEN valor ELSE 0 END), 0) AS debitos,
                COALESCE(SUM(valor), 0) AS liquido,
                SUM(CASE WHEN status = 'aprovado' THEN 1 ELSE 0 END) AS aprovados,
                SUM(CASE WHEN status = 'ignorado' THEN 1 ELSE 0 END) AS ignorados,
                SUM(CASE WHEN status NOT IN ('aprovado','ignorado') THEN 1 ELSE 0 END) AS pendentes
             FROM caixa_movimento
             WHERE id_sessao = ? AND trash = 0",
            [$idSessao]
        );
        $t = $tot[0] ?? null;

        $sessao = DB::table("caixa_sessao")->where("id", "=", $idSessao)->first();
        $esperado = (int) ($sessao->total_movimentos ?? 0);
        $qtd = (int) ($t->qtd ?? 0);

        $rec = DB::execute(
            "SELECT COUNT(*) AS qtd,
                    COALESCE(SUM(ABS(COALESCE(valor,0))), 0) AS soma
             FROM caixa_recibo
             WHERE id_sessao = ? AND trash = 0",
            [$idSessao]
        );
        $r = $rec[0] ?? null;

        $vinc = DB::execute(
            "SELECT
                COUNT(DISTINCT v.id_recibo) AS recibos_vinculados,
                COUNT(DISTINCT v.id_movimento) AS movs_com_recibo,
                COUNT(v.id) AS vinculos
             FROM caixa_vinculo v
             INNER JOIN caixa_movimento m ON m.id = v.id_movimento AND m.id_sessao = ? AND m.trash = 0
             INNER JOIN caixa_recibo r ON r.id = v.id_recibo AND r.trash = 0
             WHERE v.trash = 0",
            [$idSessao]
        );
        $v = $vinc[0] ?? null;

        $qtdRec = (int) ($r->qtd ?? 0);
        $recVinc = (int) ($v->recibos_vinculados ?? 0);

        return [
            "movimentos"           => $qtd,
            "movimentos_esperados" => $esperado,
            "movimentos_ok"        => $esperado === 0 || $esperado === $qtd,
            "creditos"             => (float) ($t->creditos ?? 0),
            "debitos"              => (float) ($t->debitos ?? 0),
            "liquido"              => (float) ($t->liquido ?? 0),
            "aprovados"            => (int) ($t->aprovados ?? 0),
            "ignorados"            => (int) ($t->ignorados ?? 0),
            "pendentes"            => (int) ($t->pendentes ?? 0),
            "recibos"              => $qtdRec,
            "recibos_vinculados"   => $recVinc,
            "recibos_sem_vinculo"  => max(0, $qtdRec - $recVinc),
            "movs_com_recibo"      => (int) ($v->movs_com_recibo ?? 0),
            "vinculos"             => (int) ($v->vinculos ?? 0),
            "soma_recibos"         => (float) ($r->soma ?? 0),
            "cobertura_recibos"    => $qtdRec > 0 ? (int) round(100 * $recVinc / $qtdRec) : 0,
        ];
    }

    /**
     * @return array<int,true> id_recibo => true
     */
    private function recibosJaVinculados(int $idSessao): array
    {
        $rows = DB::execute(
            "SELECT v.id_recibo
             FROM caixa_vinculo v
             INNER JOIN caixa_recibo r ON r.id = v.id_recibo AND r.id_sessao = ? AND r.trash = 0
             WHERE v.trash = 0",
            [$idSessao]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->id_recibo] = true;
        }
        return $out;
    }

    /**
     * Movimentos que já têm vínculo 1:1 (valor do recibo ≈ valor do movimento).
     *
     * @return array<int,true>
     */
    private function movimentosComVinculoExclusivo(int $idSessao): array
    {
        $rows = DB::execute(
            "SELECT v.id_movimento, m.valor AS mov_valor, r.valor AS rec_valor, v.origem
             FROM caixa_vinculo v
             INNER JOIN caixa_movimento m ON m.id = v.id_movimento AND m.id_sessao = ? AND m.trash = 0
             INNER JOIN caixa_recibo r ON r.id = v.id_recibo AND r.trash = 0
             WHERE v.trash = 0",
            [$idSessao]
        );
        $out = [];
        foreach ($rows as $row) {
            if (($row->origem ?? "") === "manual") {
                $out[(int) $row->id_movimento] = true;
                continue;
            }
            if ($row->rec_valor === null) {
                continue;
            }
            if (abs(abs((float) $row->mov_valor) - abs((float) $row->rec_valor)) <= 0.05) {
                $out[(int) $row->id_movimento] = true;
            }
        }
        return $out;
    }

    /**
     * @param array<int,object> $recibos
     * @param array<int,object> $movs
     * @param array<int,true> $usadosRec
     */
    private function cruzarAgregados(int $idSessao, array $recibos, array $movs, array &$usadosRec): int
    {
        $grupos = [];
        foreach ($recibos as $rec) {
            $idRec = (int) $rec->id;
            if (isset($usadosRec[$idRec])) {
                continue;
            }
            if (empty($rec->data_doc) || $rec->valor === null) {
                continue;
            }
            $identKey = !empty($rec->ident_extrato)
                ? $this->norm((string) $rec->ident_extrato)
                : "_sem_ident_";
            // Agrupa por data + prefixo da identificação (SISPAG SALARIOS, TED, etc.)
            $prefix = $this->prefixIdent($identKey);
            $k = (string) $rec->data_doc . "|" . $prefix;
            $grupos[$k][] = $rec;
        }

        $criados = 0;
        foreach ($grupos as $k => $lista) {
            if (count($lista) < 2) {
                continue;
            }
            [$data, $prefix] = explode("|", $k, 2);
            $soma = 0.0;
            foreach ($lista as $r) {
                $soma += abs((float) ($r->valor ?? 0));
            }
            $soma = round($soma, 2);

            foreach ($movs as $m) {
                if ((string) $m->data_posted !== $data) {
                    continue;
                }
                if (abs(abs((float) $m->valor) - $soma) > 0.05) {
                    continue;
                }
                $memoN = $this->norm((string) ($m->memo ?? ""));
                if ($prefix !== "_sem_ident_" && !$this->identCasaComMemo($prefix, $memoN)) {
                    // Ainda aceita se memo indica folha/salário e prefixo também
                    if (!($this->pareceFolha($prefix) && $this->pareceFolha($memoN))) {
                        continue;
                    }
                }

                foreach ($lista as $rec) {
                    $idRec = (int) $rec->id;
                    if (isset($usadosRec[$idRec])) {
                        continue;
                    }
                    if ($this->salvarVinculo(
                        (int) $m->id,
                        $idRec,
                        92,
                        $m,
                        "auto",
                        "sugerido",
                        "Match agregado (soma de comprovantes = linha do extrato)"
                    )) {
                        $criados++;
                    }
                    $usadosRec[$idRec] = true;
                }
                break;
            }
        }

        return $criados;
    }

    /**
     * Confirma vínculo sugerido no cruzamento (vira validado).
     */
    public function confirmarVinculo(int $idSessao, int $idVinculo): bool
    {
        $row = DB::execute(
            "SELECT v.id
             FROM caixa_vinculo v
             INNER JOIN caixa_movimento m ON m.id = v.id_movimento AND m.id_sessao = ? AND m.trash = 0
             WHERE v.id = ? AND v.trash = 0
             LIMIT 1",
            [$idSessao, $idVinculo]
        );
        if ($row === []) {
            return false;
        }

        DB::table("caixa_vinculo")
            ->where("id", "=", $idVinculo)
            ->update([
                "status"          => "confirmado",
                "confianca_match" => 100,
                "motivo"          => "Confirmado no cruzamento",
            ]);

        return true;
    }

    /**
     * Confirma todos os vínculos automáticos sugeridos da sessão.
     */
    public function confirmarSugeridos(int $idSessao): int
    {
        $rows = DB::execute(
            "SELECT v.id
             FROM caixa_vinculo v
             INNER JOIN caixa_movimento m ON m.id = v.id_movimento AND m.id_sessao = ? AND m.trash = 0
             WHERE v.trash = 0 AND v.status = 'sugerido'",
            [$idSessao]
        );
        $n = 0;
        foreach ($rows as $r) {
            if ($this->confirmarVinculo($idSessao, (int) $r->id)) {
                $n++;
            }
        }
        return $n;
    }

    private function salvarVinculo(
        int $idMov,
        int $idRec,
        int $score,
        object $mov,
        string $origem = "auto",
        string $status = "sugerido",
        string $motivo = "Match valor/data/identificação extrato"
    ): bool {
        $existe = DB::table("caixa_vinculo")
            ->where("id_movimento", "=", $idMov)
            ->where("id_recibo", "=", $idRec)
            ->first();

        if ($existe) {
            // Não sobrescreve vínculo manual
            if (($existe->origem ?? "") === "manual" && (int) ($existe->trash ?? 0) === 0) {
                return false;
            }
            DB::table("caixa_vinculo")
                ->where("id", "=", (int) $existe->id)
                ->update([
                    "confianca_match" => $score,
                    "motivo"          => mb_substr($motivo, 0, 255),
                    "status"          => $status,
                    "origem"          => $origem,
                    "trash"           => 0,
                ]);
            return (int) ($existe->trash ?? 0) === 1;
        }

        DB::table("caixa_vinculo")->insert([
            "id_movimento"    => $idMov,
            "id_recibo"       => $idRec,
            "confianca_match" => $score,
            "origem"          => $origem,
            "status"          => $status,
            "motivo"          => mb_substr($motivo, 0, 255),
            "trash"           => 0,
        ]);

        if (($mov->status ?? "") === "novo") {
            DB::table("caixa_movimento")
                ->where("id", "=", $idMov)
                ->update(["status" => "sugerido"]);
        }

        return true;
    }

    private function scoreMatch(object $mov, object $rec): int
    {
        $score = 0;
        $vMov = abs((float) $mov->valor);
        $vRec = $rec->valor !== null ? abs((float) $rec->valor) : null;
        $mesmoValor = false;

        if ($vRec !== null) {
            $delta = abs($vMov - $vRec);
            if ($delta <= 0.01) {
                $score += 55;
                $mesmoValor = true;
            } elseif ($delta <= 0.05) {
                $score += 50;
                $mesmoValor = true;
            } elseif ($delta <= 1.0) {
                $score += 30;
            } elseif ($delta <= 5.0) {
                $score += 12;
            } else {
                // Linha agregada: valor diferente é esperado
                if (empty($rec->ident_extrato)) {
                    return 0;
                }
                $score += 5;
            }
        }

        $mesmaData = false;
        if (!empty($rec->data_doc) && !empty($mov->data_posted)) {
            $d1 = strtotime((string) $mov->data_posted);
            $d2 = strtotime((string) $rec->data_doc);
            if ($d1 && $d2) {
                $dias = abs($d1 - $d2) / 86400;
                if ($dias <= 0) {
                    $score += 30;
                    $mesmaData = true;
                } elseif ($dias <= 1) {
                    $score += 25;
                    $mesmaData = true;
                } elseif ($dias <= 3) {
                    $score += 15;
                } elseif ($dias <= 7) {
                    $score += 8;
                }
            }
        }

        // Valor exato + mesma data já é match forte mesmo sem texto
        if ($mesmoValor && $mesmaData) {
            $score = max($score, 85);
        }

        $memoN = $this->norm((string) ($mov->memo ?? ""));

        if (!empty($rec->ident_extrato)) {
            $identN = $this->norm((string) $rec->ident_extrato);
            if ($this->identCasaComMemo($identN, $memoN)) {
                $score += 28;
            } elseif ($this->pareceFolha($identN) && $this->pareceFolha($memoN)) {
                $score += 20;
            } else {
                foreach (preg_split('/\s+/u', $identN) ?: [] as $tok) {
                    if (mb_strlen($tok) >= 4 && str_contains($memoN, $tok)) {
                        $score += 18;
                        break;
                    }
                }
            }
        }

        if (!empty($rec->contraparte)) {
            $nomeN = $this->norm((string) $rec->contraparte);
            $partes = preg_split('/\s+/u', $nomeN) ?: [];
            $hits = 0;
            foreach ($partes as $p) {
                if (mb_strlen($p) >= 4 && str_contains($memoN, $p)) {
                    $hits++;
                }
            }
            if ($hits >= 2) {
                $score += 18;
            } elseif ($hits === 1) {
                $score += 10;
            }
        }

        // Texto bruto do comprovante × memo
        if (!empty($rec->texto_extraido) && $memoN !== "") {
            $txt = $this->norm(mb_substr((string) $rec->texto_extraido, 0, 1200));
            foreach (["sispag", "salario", "salarios", "ted", "pix", "fornecedor"] as $kw) {
                if (str_contains($txt, $kw) && str_contains($memoN, $kw)) {
                    $score += 6;
                    break;
                }
            }
        }

        return min(100, $score);
    }

    private function motivoScore(int $score, object $mov, object $rec): string
    {
        $partes = [];
        if ($rec->valor !== null && abs(abs((float) $mov->valor) - abs((float) $rec->valor)) <= 0.05) {
            $partes[] = "valor";
        }
        if (!empty($rec->data_doc) && (string) $rec->data_doc === (string) $mov->data_posted) {
            $partes[] = "data";
        }
        if (!empty($rec->ident_extrato)) {
            $partes[] = "identificação";
        }
        if (!empty($rec->contraparte)) {
            $partes[] = "contraparte";
        }
        $base = $partes !== [] ? "Match " . implode("+", $partes) : "Match automático";
        return $base . " ({$score}%)";
    }

    private function norm(string $s): string
    {
        $s = mb_strtoupper(trim($s), "UTF-8");
        $map = [
            "Á" => "A", "À" => "A", "Ã" => "A", "Â" => "A", "Ä" => "A",
            "É" => "E", "Ê" => "E", "È" => "E",
            "Í" => "I", "Ì" => "I", "Î" => "I",
            "Ó" => "O", "Ô" => "O", "Õ" => "O", "Ò" => "O",
            "Ú" => "U", "Ù" => "U", "Ü" => "U",
            "Ç" => "C",
        ];
        $s = strtr($s, $map);
        $s = preg_replace('/[^A-Z0-9\s]+/u', " ", $s) ?? $s;
        return trim(preg_replace('/\s+/u', " ", $s) ?? $s);
    }

    private function prefixIdent(string $identNorm): string
    {
        if ($identNorm === "" || $identNorm === "_SEM_IDENT_") {
            return "_sem_ident_";
        }
        $toks = preg_split('/\s+/u', $identNorm) ?: [];
        $take = array_slice($toks, 0, min(3, count($toks)));
        return implode(" ", $take) ?: "_sem_ident_";
    }

    private function identCasaComMemo(string $identNorm, string $memoNorm): bool
    {
        if ($identNorm === "" || $memoNorm === "") {
            return false;
        }
        if (str_contains($memoNorm, $identNorm) || str_contains($identNorm, $memoNorm)) {
            return true;
        }
        $toks = array_values(array_filter(
            preg_split('/\s+/u', $identNorm) ?: [],
            static fn ($t) => mb_strlen($t) >= 4
        ));
        if ($toks === []) {
            return false;
        }
        $hits = 0;
        foreach ($toks as $t) {
            if (str_contains($memoNorm, $t)) {
                $hits++;
            }
        }
        return $hits >= min(2, count($toks));
    }

    private function pareceFolha(string $norm): bool
    {
        return str_contains($norm, "SISPAG")
            || str_contains($norm, "SALARIO")
            || str_contains($norm, "FOLHA")
            || str_contains($norm, "PROLABORE")
            || str_contains($norm, "PRO LABORE");
    }
}
