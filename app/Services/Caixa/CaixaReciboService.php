<?php

namespace App\Services\Caixa;

use App\Core\DB;
use App\Models\CaixaRecibo;

/**
 * Upload de recibos + cruzamento simples com movimentos (valor/data/texto).
 */
class CaixaReciboService
{
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
        $ids = [];

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

            $texto = $ext === "pdf" ? $this->extrairTextoPdf($destino) : "";
            $extra = $this->inferirCampos($texto, $nome);

            $ins = DB::table("caixa_recibo")->insert([
                "id_sessao"         => $idSessao,
                "arquivo_path"      => "recibos/" . $salvo,
                "nome_original"     => mb_substr($nome, 0, 255),
                "data_doc"          => $extra["data"],
                "valor"             => $extra["valor"],
                "texto_extraido"    => $texto !== "" ? mb_substr($texto, 0, 50000) : null,
                "contraparte"       => $extra["contraparte"],
                "status_extracao"   => $texto !== "" || $extra["valor"] !== null ? "ok" : "pendente",
                "trash"             => 0,
            ]);
            $id = (int) ($ins->id ?? 0);
            if ($id > 0) {
                $ids[] = $id;
                $criados++;
            }
        }

        $vinculos = 0;
        if ($ids !== []) {
            $vinculos = $this->cruzarSessao($idSessao);
        }

        return ["criados" => $criados, "vinculos" => $vinculos, "avisos" => $avisos];
    }

    public function cruzarSessao(int $idSessao): int
    {
        $recibos = CaixaRecibo::porSessao($idSessao);
        $movs = DB::table("caixa_movimento")
            ->where("id_sessao", "=", $idSessao)
            ->where("trash", "=", 0)
            ->whereNotIn("status", ["ignorado"])
            ->get();

        $criados = 0;
        foreach ($recibos as $rec) {
            if ($rec->valor === null && !$rec->data_doc) {
                continue;
            }
            $melhor = null;
            $melhorScore = 0;
            foreach ($movs as $m) {
                $score = $this->scoreMatch($m, $rec);
                if ($score > $melhorScore) {
                    $melhorScore = $score;
                    $melhor = $m;
                }
            }
            if ($melhor === null || $melhorScore < 50) {
                continue;
            }

            $existe = DB::table("caixa_vinculo")
                ->where("id_movimento", "=", (int) $melhor->id)
                ->where("id_recibo", "=", (int) $rec->id)
                ->where("trash", "=", 0)
                ->first();
            if ($existe) {
                DB::table("caixa_vinculo")
                    ->where("id", "=", (int) $existe->id)
                    ->update([
                        "confianca_match" => $melhorScore,
                        "motivo"          => "Match automático valor/data/texto",
                        "status"          => "sugerido",
                        "origem"          => "auto",
                    ]);
            } else {
                DB::table("caixa_vinculo")->insert([
                    "id_movimento"    => (int) $melhor->id,
                    "id_recibo"       => (int) $rec->id,
                    "confianca_match" => $melhorScore,
                    "origem"          => "auto",
                    "status"          => "sugerido",
                    "motivo"          => "Match automático valor/data/texto",
                    "trash"           => 0,
                ]);
                $criados++;
            }

            // Se movimento ainda novo, sobe status para sugerido
            if (($melhor->status ?? "") === "novo") {
                DB::table("caixa_movimento")
                    ->where("id", "=", (int) $melhor->id)
                    ->update(["status" => "sugerido"]);
            }
        }

        return $criados;
    }

    private function scoreMatch(object $mov, object $rec): int
    {
        $score = 0;
        $vMov = abs((float) $mov->valor);
        $vRec = $rec->valor !== null ? abs((float) $rec->valor) : null;

        if ($vRec !== null) {
            $delta = abs($vMov - $vRec);
            if ($delta <= 0.01) {
                $score += 55;
            } elseif ($delta <= 1.0) {
                $score += 35;
            } elseif ($delta <= 5.0) {
                $score += 15;
            } else {
                return 0;
            }
        }

        if (!empty($rec->data_doc) && !empty($mov->data_posted)) {
            $d1 = strtotime((string) $mov->data_posted);
            $d2 = strtotime((string) $rec->data_doc);
            if ($d1 && $d2) {
                $dias = abs($d1 - $d2) / 86400;
                if ($dias <= 0) {
                    $score += 30;
                } elseif ($dias <= 2) {
                    $score += 22;
                } elseif ($dias <= 5) {
                    $score += 10;
                }
            }
        }

        $texto = mb_strtolower((string) ($rec->texto_extraido ?? "") . " " . (string) ($rec->nome_original ?? ""));
        $memo  = mb_strtolower((string) ($mov->memo ?? ""));
        if ($texto !== "" && $memo !== "") {
            $tokens = preg_split('/\s+/', preg_replace('/[^a-z0-9à-ü\s]/iu', " ", $memo) ?? "") ?: [];
            $hits = 0;
            foreach ($tokens as $t) {
                if (mb_strlen($t) < 4) {
                    continue;
                }
                if (str_contains($texto, $t)) {
                    $hits++;
                }
            }
            $score += min(20, $hits * 5);
        }

        return min(100, $score);
    }

    private function extrairTextoPdf(string $path): string
    {
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === "") {
            return "";
        }
        $out = [];
        // Extrai strings literais simples de PDFs digitais
        if (preg_match_all('/\((?:\\\\.|[^\\\\)]){3,}\)/s', $raw, $m)) {
            foreach ($m[0] as $chunk) {
                $s = substr($chunk, 1, -1);
                $s = str_replace(["\\n", "\\r", "\\t", "\\(", "\\)"], [" ", " ", " ", "(", ")"], $s);
                $s = preg_replace('/\\\\[0-9]{3}/', "", $s) ?? $s;
                if (preg_match('/[A-Za-zÀ-ü0-9]{3,}/u', $s)) {
                    $out[] = $s;
                }
            }
        }
        $texto = trim(implode(" ", $out));
        if (mb_strlen($texto) > 8000) {
            $texto = mb_substr($texto, 0, 8000);
        }
        return $texto;
    }

    /**
     * @return array{data:?string,valor:?float,contraparte:?string}
     */
    private function inferirCampos(string $texto, string $nomeArquivo): array
    {
        $blob = $texto . " " . $nomeArquivo;
        $valor = null;
        if (preg_match('/R\$\s*([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2}|[0-9]+,[0-9]{2})/u', $blob, $m)) {
            $valor = (float) str_replace([".", ","], ["", "."], $m[1]);
        } elseif (preg_match('/\b([0-9]{1,3}(?:\.[0-9]{3})*,[0-9]{2})\b/', $blob, $m)) {
            $valor = (float) str_replace([".", ","], ["", "."], $m[1]);
        }

        $data = null;
        if (preg_match('/\b([0-3]\d)[\/\-]([0-1]\d)[\/\-](20\d{2})\b/', $blob, $m)) {
            $data = sprintf("%s-%s-%s", $m[3], $m[2], $m[1]);
        } elseif (preg_match('/\b(20\d{2})[\/\-]([0-1]\d)[\/\-]([0-3]\d)\b/', $blob, $m)) {
            $data = sprintf("%s-%s-%s", $m[1], $m[2], $m[3]);
        }

        $contraparte = null;
        if (preg_match('/(?:favorecido|beneficiario|razao social|nome)[:\s]+([A-Za-zÀ-ü0-9 .&\-]{5,80})/iu', $blob, $m)) {
            $contraparte = trim($m[1]);
        }

        return ["data" => $data, "valor" => $valor, "contraparte" => $contraparte];
    }
}
