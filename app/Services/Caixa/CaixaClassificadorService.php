<?php

namespace App\Services\Caixa;

use App\Core\DB;
use App\Lib\ChatGPT;
use App\Models\CaixaMovimento;
use App\Models\DreConta;

/**
 * Classifica movimentos do extrato em contas do plano DFC.
 * Heurística + memória do projeto primeiro; IA só escolhe entre IDs válidos.
 */
class CaixaClassificadorService
{
    private const TIPO = "dfc";

    /**
     * Classifica movimentos ainda sem conta (ou força todos não aprovados/ignorados).
     *
     * @return array{atualizados:int, por_memoria:int, por_regra:int, por_ia:int, avisos:array<int,string>}
     */
    public function classificarSessao(int $idSessao, int $idProjeto, bool $usarIa = true, bool $soPendentes = true): array
    {
        DreConta::garantirPlanoSagaPadrao(self::TIPO);

        $contas = DreConta::analiticasLista(self::TIPO);
        $whitelist = [];
        foreach ($contas as $c) {
            $whitelist[(int) $c->id] = $c;
        }

        $q = DB::table("caixa_movimento")
            ->where("id_sessao", "=", $idSessao)
            ->where("trash", "=", 0);
        if ($soPendentes) {
            $q->whereIn("status", ["novo", "sugerido"]);
        } else {
            $q->whereNotIn("status", ["aprovado", "ignorado"]);
        }
        $movs = $q->orderBy("id")->get();

        $memoria = $this->memoriaProjeto($idProjeto);
        $atualizados = 0;
        $porMemoria = 0;
        $porRegra = 0;
        $porIa = 0;
        $avisos = [];
        $ambiguos = [];

        foreach ($movs as $m) {
            $memo = (string) ($m->memo ?? "");
            $chave = $this->chaveMemo($memo);

            if ($chave !== "" && isset($memoria[$chave])) {
                $this->aplicar((int) $m->id, (int) $memoria[$chave]["id_conta"], 95, "Memória do projeto (já aprovado)", "sugerido");
                $atualizados++;
                $porMemoria++;
                continue;
            }

            $regra = $this->porRegra($memo, (float) $m->valor, $whitelist);
            if ($regra !== null) {
                $this->aplicar((int) $m->id, $regra["id"], $regra["confianca"], $regra["motivo"], "sugerido");
                $atualizados++;
                $porRegra++;
                continue;
            }

            $ambiguos[] = $m;
        }

        if ($usarIa && $ambiguos !== []) {
            $ia = $this->classificarComIa($ambiguos, $whitelist);
            foreach ($ia["aplicados"] as $row) {
                $this->aplicar($row["id"], $row["id_conta"], $row["confianca"], $row["motivo"], "sugerido");
                $atualizados++;
                $porIa++;
            }
            foreach ($ia["avisos"] as $a) {
                $avisos[] = $a;
            }
        }

        return [
            "atualizados"  => $atualizados,
            "por_memoria"  => $porMemoria,
            "por_regra"    => $porRegra,
            "por_ia"       => $porIa,
            "avisos"       => $avisos,
        ];
    }

    /**
     * @param array<int,object> $whitelist id=>conta
     * @return array{id:int,confianca:int,motivo:string}|null
     */
    private function porRegra(string $memo, float $valor, array $whitelist): ?array
    {
        $n = $this->norm($memo);
        $mapa = $this->indiceContas($whitelist);

        $regras = [
            ["rx" => "/pixrecebido|tedrecebid|docrecebid|deposito|creditode|vendas|client/", "nome" => "Recebimentos de Clientes", "conf" => 82, "sinal" => 1],
            ["rx" => "/rendiment|jurosreceb|aplicacao|rendafixa/", "nome" => "Rendimentos Financeiros Recebidos", "conf" => 80, "sinal" => 1],
            ["rx" => "/fornecedor|compra|material|mercadoria|estoque/", "nome" => "Pagamentos a Fornecedores", "conf" => 80, "sinal" => -1],
            ["rx" => "/aluguel|energia|internet|telefone|escritorio|despesa|salario|folha|inss|fgts/", "nome" => "Pagamento de Despesas Operacionais", "conf" => 75, "sinal" => -1],
            ["rx" => "/tributo|imposto|das|iss|icms|irrf|darf|gps/", "nome" => "Pagamento de Tributos", "conf" => 85, "sinal" => -1],
            ["rx" => "/imobilizado|equipamento|maquina|veiculo|capex/", "nome" => "Aquisição de Imobilizado (Capex)", "conf" => 78, "sinal" => -1],
            ["rx" => "/emprestimoreceb|captacao|financiamentoreceb/", "nome" => "Captação de Empréstimos", "conf" => 78, "sinal" => 1],
            ["rx" => "/amortizacao|parceladeemprest|pagamentodeemprest/", "nome" => "Amortização de Empréstimos", "conf" => 78, "sinal" => -1],
            ["rx" => "/jurospag|jurosdeemprest/", "nome" => "Juros Pagos", "conf" => 80, "sinal" => -1],
            ["rx" => "/tarifa|taxa banc|iof|anuidade/", "nome" => "Pagamento de Despesas Operacionais", "conf" => 70, "sinal" => -1],
            ["rx" => "/pixenviad|tedenviad|docenviad|boleto|pagamento/", "nome" => "Pagamento de Despesas Operacionais", "conf" => 65, "sinal" => -1],
        ];

        foreach ($regras as $r) {
            if (!preg_match($r["rx"], $n)) {
                continue;
            }
            if ($r["sinal"] > 0 && $valor < 0) {
                continue;
            }
            if ($r["sinal"] < 0 && $valor > 0) {
                continue;
            }
            $id = $mapa[$this->norm($r["nome"])] ?? null;
            if ($id === null) {
                continue;
            }
            return [
                "id"        => $id,
                "confianca" => $r["conf"],
                "motivo"    => "Regra: " . $r["nome"],
            ];
        }

        return null;
    }

    /**
     * @param array<int,object> $movs
     * @param array<int,object> $whitelist
     * @return array{aplicados:array<int,array{id:int,id_conta:int,confianca:int,motivo:string}>,avisos:array<int,string>}
     */
    private function classificarComIa(array $movs, array $whitelist): array
    {
        $aplicados = [];
        $avisos = [];

        // Agrupa por memo normalizado para não gastar 1 call por linha idêntica
        $grupos = [];
        foreach ($movs as $m) {
            $k = $this->chaveMemo((string) $m->memo);
            if ($k === "") {
                $k = "id:" . (int) $m->id;
            }
            $grupos[$k][] = $m;
        }

        $candidatos = [];
        foreach ($whitelist as $c) {
            $candidatos[] = [
                "id"     => (int) $c->id,
                "codigo" => (string) ($c->codigo ?? ""),
                "nome"   => (string) ($c->nome ?? ""),
            ];
        }

        try {
            $ai = new ChatGPT();
        } catch (\Throwable $e) {
            return ["aplicados" => [], "avisos" => ["IA indisponível: " . $e->getMessage()]];
        }

        $chunks = array_chunk(array_keys($grupos), 25);
        foreach ($chunks as $keys) {
            $itens = [];
            foreach ($keys as $k) {
                $sample = $grupos[$k][0];
                $itens[] = [
                    "chave"  => $k,
                    "memo"   => (string) $sample->memo,
                    "valor"  => (float) $sample->valor,
                    "tipo"   => (string) $sample->tipo,
                    "data"   => (string) $sample->data_posted,
                ];
            }

            $system = <<<PROMPT
Você classifica lançamentos de extrato bancário no plano DFC.
Responda SOMENTE JSON: {"itens":[{"chave":"...","id_conta":123,"confianca":0-100,"motivo":"..."}]}
Regras:
- id_conta DEVE ser um dos IDs da lista de contas. Senão omita o item.
- Não invente conta. Se incerto, confianca baixa (30-49) ou omita.
- grupo implícito: operacional / investimento / financiamento conforme a conta.
- motivo curto em português.
PROMPT;

            $prompt  = "CONTAS DFC (whitelist):\n" . json_encode($candidatos, JSON_UNESCAPED_UNICODE) . "\n\n";
            $prompt .= "MOVIMENTOS:\n" . json_encode($itens, JSON_UNESCAPED_UNICODE);

            $result = $ai->send($system, $prompt, "", null, [
                "retries" => 0,
                "model"   => "gpt-4.1-mini",
            ]);

            if (!$result["ok"]) {
                $avisos[] = "IA falhou em um lote: " . ($result["error"] ?? "erro");
                continue;
            }

            $text = trim((string) ($result["text"] ?? ""));
            $text = preg_replace('/^```(?:json)?\s*/i', "", $text) ?? $text;
            $text = preg_replace('/\s*```$/', "", $text) ?? $text;
            $decoded = json_decode($text, true);
            if (!is_array($decoded) || !is_array($decoded["itens"] ?? null)) {
                $avisos[] = "IA devolveu JSON inválido em um lote.";
                continue;
            }

            foreach ($decoded["itens"] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $chave = (string) ($item["chave"] ?? "");
                $idConta = (int) ($item["id_conta"] ?? 0);
                $conf = max(0, min(100, (int) ($item["confianca"] ?? 0)));
                $motivo = trim((string) ($item["motivo"] ?? "Sugestão IA"));
                if ($chave === "" || !isset($grupos[$chave]) || !isset($whitelist[$idConta])) {
                    continue;
                }
                if ($conf < 30) {
                    continue;
                }
                foreach ($grupos[$chave] as $m) {
                    $aplicados[] = [
                        "id"        => (int) $m->id,
                        "id_conta"  => $idConta,
                        "confianca" => $conf,
                        "motivo"    => "IA: " . mb_substr($motivo, 0, 200),
                    ];
                }
            }
        }

        return ["aplicados" => $aplicados, "avisos" => $avisos];
    }

    private function aplicar(int $idMov, int $idConta, int $conf, string $motivo, string $status): void
    {
        DB::table("caixa_movimento")
            ->where("id", "=", $idMov)
            ->update([
                "id_dre_conta"     => $idConta,
                "confianca_conta"  => max(0, min(100, $conf)),
                "motivo_conta"     => mb_substr($motivo, 0, 255),
                "status"           => $status,
            ]);
    }

    /**
     * @return array<string,array{id_conta:int}>
     */
    private function memoriaProjeto(int $idProjeto): array
    {
        $rows = DB::execute(
            "SELECT m.memo, m.id_dre_conta
             FROM caixa_movimento m
             INNER JOIN caixa_sessao s ON s.id = m.id_sessao AND s.trash = 0
             WHERE s.id_projeto = ?
               AND m.trash = 0
               AND m.status IN ('aprovado','editado')
               AND m.id_dre_conta IS NOT NULL
             ORDER BY m.id DESC
             LIMIT 2000",
            [$idProjeto]
        );

        $out = [];
        foreach ($rows as $r) {
            $k = $this->chaveMemo((string) ($r->memo ?? ""));
            if ($k === "" || isset($out[$k])) {
                continue;
            }
            $out[$k] = ["id_conta" => (int) $r->id_dre_conta];
        }
        return $out;
    }

    /**
     * @param array<int,object> $whitelist
     * @return array<string,int>
     */
    private function indiceContas(array $whitelist): array
    {
        $idx = [];
        foreach ($whitelist as $c) {
            $idx[$this->norm((string) $c->nome)] = (int) $c->id;
        }
        return $idx;
    }

    private function chaveMemo(string $memo): string
    {
        $n = $this->norm($memo);
        // Remove números longos (ids de cliente) para agrupar padrões
        $n = preg_replace('/\d{3,}/', "#", $n) ?? $n;
        return $n;
    }

    private function norm(string $t): string
    {
        $t = mb_strtolower(trim($t), "UTF-8");
        $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $t);
        $t = is_string($ascii) ? $ascii : $t;
        return (string) preg_replace("/[^a-z0-9]+/", "", $t);
    }
}
