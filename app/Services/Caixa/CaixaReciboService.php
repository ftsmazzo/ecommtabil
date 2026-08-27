<?php

namespace App\Services\Caixa;

use App\Core\DB;
use App\Models\CaixaRecibo;

/**
 * Upload de recibos + cruzamento com movimentos (valor/data/texto/identificação no extrato).
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
                    $itens = $this->comprovanteParser->parseFile($destino);
                } catch (\Throwable $e) {
                    $itens = [[
                        "valor"         => null,
                        "data"          => null,
                        "contraparte"   => null,
                        "ident_extrato" => null,
                        "texto"         => $this->extractor->extract($destino),
                    ]];
                    $avisos[] = "PDF {$nome}: parse parcial — " . $e->getMessage();
                }

                if ($itens === []) {
                    $itens = [[
                        "valor" => null, "data" => null, "contraparte" => null,
                        "ident_extrato" => null, "texto" => $this->extractor->extract($destino),
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
                $texto = "";
                $extra = ["data" => null, "valor" => null, "contraparte" => null, "ident_extrato" => null, "texto" => ""];
                if ($this->inserirRecibo($idSessao, "recibos/" . $salvo, $nome, array_merge($extra, ["texto" => $texto]))) {
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

        $criados = 0;

        // 1) Match 1:1 valor+data+ident
        foreach ($recibos as $rec) {
            $melhor = null;
            $melhorScore = 0;
            foreach ($movs as $m) {
                $score = $this->scoreMatch($m, $rec);
                if ($score > $melhorScore) {
                    $melhorScore = $score;
                    $melhor = $m;
                }
            }
            if ($melhor !== null && $melhorScore >= 50) {
                if ($this->salvarVinculo((int) $melhor->id, (int) $rec->id, $melhorScore, $melhor)) {
                    $criados++;
                }
            }
        }

        // 2) Match N:1 — vários recibos somam linha agregada (ex. SISPAG SALARIOS)
        $criados += $this->cruzarAgregados($idSessao, $recibos, $movs);

        return $criados;
    }

    /**
     * @param array<int,object> $recibos
     * @param array<int,object> $movs
     */
    private function cruzarAgregados(int $idSessao, array $recibos, array $movs): int
    {
        $grupos = [];
        foreach ($recibos as $rec) {
            if (empty($rec->ident_extrato) || empty($rec->data_doc)) {
                continue;
            }
            $k = trim((string) $rec->ident_extrato) . "|" . (string) $rec->data_doc;
            $grupos[$k][] = $rec;
        }

        $criados = 0;
        foreach ($grupos as $k => $lista) {
            if (count($lista) < 2) {
                continue;
            }
            [$ident, $data] = explode("|", $k, 2);
            $soma = 0.0;
            foreach ($lista as $r) {
                $soma += abs((float) ($r->valor ?? 0));
            }
            $soma = round($soma, 2);

            foreach ($movs as $m) {
                $memo = mb_strtoupper((string) ($m->memo ?? ""), "UTF-8");
                $identU = mb_strtoupper(trim($ident), "UTF-8");
                if (!str_contains($memo, trim(explode(" ", $identU)[0]))) {
                    continue;
                }
                if ((string) $m->data_posted !== $data) {
                    continue;
                }
                if (abs(abs((float) $m->valor) - $soma) > 0.05) {
                    continue;
                }
                $score = 92;
                foreach ($lista as $rec) {
                    if ($this->salvarVinculo((int) $m->id, (int) $rec->id, $score, $m)) {
                        $criados++;
                    }
                }
                break;
            }
        }

        return $criados;
    }

    private function salvarVinculo(int $idMov, int $idRec, int $score, object $mov): bool
    {
        $existe = DB::table("caixa_vinculo")
            ->where("id_movimento", "=", $idMov)
            ->where("id_recibo", "=", $idRec)
            ->where("trash", "=", 0)
            ->first();

        if ($existe) {
            DB::table("caixa_vinculo")
                ->where("id", "=", (int) $existe->id)
                ->update([
                    "confianca_match" => $score,
                    "motivo"          => "Match valor/data/identificação extrato",
                    "status"          => "sugerido",
                    "origem"          => "auto",
                ]);
            return false;
        }

        DB::table("caixa_vinculo")->insert([
            "id_movimento"    => $idMov,
            "id_recibo"       => $idRec,
            "confianca_match" => $score,
            "origem"          => "auto",
            "status"          => "sugerido",
            "motivo"          => "Match valor/data/identificação extrato",
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

        if ($vRec !== null) {
            $delta = abs($vMov - $vRec);
            if ($delta <= 0.01) {
                $score += 55;
            } elseif ($delta <= 1.0) {
                $score += 35;
            } elseif ($delta <= 5.0) {
                $score += 15;
            } else {
                // Pode ser linha agregada — ident_extrato salva
                if (empty($rec->ident_extrato)) {
                    return 0;
                }
                $score += 5;
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

        if (!empty($rec->ident_extrato)) {
            $ident = mb_strtoupper(trim((string) $rec->ident_extrato), "UTF-8");
            $memo  = mb_strtoupper((string) ($mov->memo ?? ""), "UTF-8");
            foreach (preg_split('/\s+/u', $ident) ?: [] as $tok) {
                if (mb_strlen($tok) >= 4 && str_contains($memo, $tok)) {
                    $score += 25;
                    break;
                }
            }
        }

        if (!empty($rec->contraparte)) {
            $nome = mb_strtoupper((string) $rec->contraparte, "UTF-8");
            $memo = mb_strtoupper((string) ($mov->memo ?? ""), "UTF-8");
            $partes = preg_split('/\s+/u', $nome) ?: [];
            if (isset($partes[0]) && mb_strlen($partes[0]) >= 4 && str_contains($memo, $partes[0])) {
                $score += 10;
            }
        }

        return min(100, $score);
    }
}
