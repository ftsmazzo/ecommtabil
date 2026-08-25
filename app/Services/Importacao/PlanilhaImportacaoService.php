<?php

namespace App\Services\Importacao;

use App\Core\DB;
use App\Models\DreConta;
use App\Models\ModeloDemonstrativo;
use App\Models\PlanilhaModeloColuna;
use App\Models\ProjetoLancamento;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PlanilhaImportacaoService
{
    public const LAYOUT_COLUNAR = "colunar";
    public const LAYOUT_LEDGER  = "ledger";
    public const LAYOUT_MATRIZ  = "matriz";

    private bool $gravar = true;

    /** @var array<int,array{linha:int,conta:string,periodo:?string,valor:float}> */
    private array $amostras = [];

    public const DEST_PERIODO   = "__periodo__";
    public const DEST_DESCRICAO = "__descricao__";
    public const DEST_VALOR     = "__valor__";
    public const DEST_UNIDADE   = "__unidade__";
    public const DEST_CONTA     = "__conta__";
    public const DEST_MATRIZ    = "__matriz__";

    /**
     * @return array{spreadsheet: \PhpOffice\PhpSpreadsheet\Spreadsheet, sheetNames: array<int,string>}
     */
    public function abrir(string $caminho): array
    {
        $reader      = IOFactory::createReaderForFile($caminho);
        $spreadsheet = $reader->load($caminho);

        return [
            "spreadsheet" => $spreadsheet,
            "sheetNames"  => $spreadsheet->getSheetNames(),
        ];
    }

    /**
     * @return array{headers: array<int,string>, previews: array<int,array<int,string>>, highestRow: int, highestCol: int, linhaCabecalho: int}
     */
    public function lerCabecalhos(Worksheet $sheet, int $linhasPreview = 3): array
    {
        $highestCol = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $highestRow = (int) $sheet->getHighestDataRow();
        $linhaCab   = $this->detectarLinhaCabecalho($sheet, $highestCol, $highestRow);
        $headers    = [];
        $previews   = [];

        for ($c = 1; $c <= $highestCol; $c++) {
            $header = $this->celula($sheet, $linhaCab, $c, false);
            $letra  = Coordinate::stringFromColumnIndex($c);

            $preview = [];
            $inicio  = $linhaCab + 1;
            $limite  = min($linhaCab + $linhasPreview, $highestRow);
            for ($r = $inicio; $r <= $limite; $r++) {
                $val = trim((string) $this->celula($sheet, $r, $c, false));
                if ($val !== "") {
                    $preview[] = $val;
                }
            }

            $headers[]  = $header !== "" ? $header : "Coluna " . $letra;
            $previews[] = $preview;
        }

        return [
            "headers"         => $headers,
            "previews"        => $previews,
            "highestRow"      => $highestRow,
            "highestCol"      => $highestCol,
            "linhaCabecalho"  => $linhaCab,
        ];
    }

    /**
     * Planilhas do Mercado Livre / Shopee costumam ter título ou filtros nas primeiras linhas.
     */
    public function detectarLinhaCabecalho(Worksheet $sheet, int $highestCol, int $highestRow): int
    {
        $limite = min(15, max(1, $highestRow));
        $melhor = 1;
        $melhorScore = -1;

        for ($r = 1; $r <= $limite; $r++) {
            $texto = 0;
            $numero = 0;
            $bonus = 0;
            for ($c = 1; $c <= $highestCol; $c++) {
                $val = trim((string) $this->celula($sheet, $r, $c, false));
                if ($val === "") {
                    continue;
                }
                $n = $this->normalizar($val);
                if ($n === "") {
                    continue;
                }
                if (preg_match('/^-?\d+([.,]\d+)?$/', $val) || preg_match('/^\d{1,2}[\/\-]\d{1,2}/', $val)) {
                    $numero++;
                    continue;
                }
                $texto++;
                if (
                    $this->parecePeriodo($n) || $this->pareceValor($n) || $this->pareceDescricao($n)
                    || $this->pareceConta($n) || $this->pareceUnidade($n)
                ) {
                    $bonus += 4;
                }
                if (preg_match('/(data|fecha|titulo|title|sku|pedido|order|total|receita|ingreso|anuncio|produto|product|venda|venta)/', $n)) {
                    $bonus += 2;
                }
            }
            if ($texto < 2) {
                continue;
            }
            $score = ($texto * 2) + $bonus - $numero;
            if ($score > $melhorScore) {
                $melhorScore = $score;
                $melhor = $r;
            }
        }

        return $melhor;
    }

    /**
     * @param array<int,string> $headers
     */
    public function detectarLayout(array $headers): string
    {
        $norm = array_map([$this, "normalizar"], $headers);

        $temPeriodo = false;
        $temConta   = false;
        $temValor   = false;
        $periodos   = 0;

        foreach ($norm as $h) {
            if ($this->parecePeriodo($h)) {
                $temPeriodo = true;
            }
            if ($this->pareceConta($h)) {
                $temConta = true;
            }
            if ($this->pareceValor($h)) {
                $temValor = true;
            }
            if ($this->pareceCabecalhoPeriodo($h)) {
                $periodos++;
            }
        }

        if ($temPeriodo && $temConta && $temValor) {
            return self::LAYOUT_LEDGER;
        }

        $primeira = $norm[0] ?? "";
        $primeiraEConta = $primeira !== ""
            && !$this->parecePeriodo($primeira)
            && !$this->pareceCabecalhoPeriodo($primeira)
            && !$this->pareceValor($primeira);

        if ($periodos >= 2 && !$temValor && ($temConta || $primeiraEConta)) {
            return self::LAYOUT_MATRIZ;
        }

        return self::LAYOUT_COLUNAR;
    }

    /**
     * Infere o layout a partir do mapeamento já gravado (sem coluna extra no banco).
     *
     * @param array<int,string> $mapa indice_coluna => destino
     */
    public function layoutDoMapa(array $mapa, ?int $idContaPadrao = null): string
    {
        $destinos = array_values($mapa);
        $temConta  = in_array(self::DEST_CONTA, $destinos, true);
        $temValor  = in_array(self::DEST_VALOR, $destinos, true);
        $temMatriz = false;
        $temContaN = false;
        foreach ($destinos as $destino) {
            $destino = (string) $destino;
            if (self::ehDestinoMatriz($destino)) {
                $temMatriz = true;
            }
            if (str_starts_with($destino, "conta_")) {
                $temContaN = true;
            }
        }

        if ($temConta && $temMatriz) {
            return self::LAYOUT_MATRIZ;
        }

        $temPadrao = $idContaPadrao !== null && $idContaPadrao > 0;
        if ($temValor && ($temConta || $temPadrao) && !$temContaN) {
            return self::LAYOUT_LEDGER;
        }

        return self::LAYOUT_COLUNAR;
    }

    public static function ehDestinoMatriz(string $destino): bool
    {
        return $destino === self::DEST_MATRIZ
            || str_starts_with($destino, self::DEST_MATRIZ . ":");
    }

    /**
     * Converte o form invertido (campo do modelo → índice da coluna) para o formato do banco.
     *
     * @param array<string,mixed> $campos
     * @param array<int,string>   $headers
     * @param array<int,mixed>    $periodosMatriz
     * @return array<int,array{indice_coluna:int,header_original:string,mapeamento:string}>
     */
    public function compactarCampos(array $campos, array $headers, array $periodosMatriz = [], ?int $anoBase = null): array
    {
        $linhas = [];
        $usados = [];

        foreach ($campos as $destino => $indice) {
            $destino = trim((string) $destino);
            if ($destino === "" || $indice === "" || $indice === null) {
                continue;
            }
            $col = (int) $indice;
            if ($col < 0 || isset($usados[$col])) {
                continue;
            }
            $usados[$col] = $destino;
            $linhas[] = [
                "indice_coluna"   => $col,
                "header_original" => trim((string) ($headers[$col] ?? "Col " . ($col + 1))),
                "mapeamento"      => $destino,
            ];
        }

        $ord = array_values(array_unique(array_map("intval", $periodosMatriz)));
        sort($ord);
        $offset = 0;

        foreach ($ord as $col) {
            if ($col < 0 || isset($usados[$col])) {
                continue;
            }
            $header = trim((string) ($headers[$col] ?? "Col " . ($col + 1)));
            $parsed = $this->parsePeriodo($header, $header, $anoBase);
            if ($parsed) {
                $destino = self::DEST_MATRIZ . ":" . substr($parsed, 0, 7);
            } elseif ($anoBase && $anoBase >= 1990 && $anoBase <= 2100) {
                $destino = self::DEST_MATRIZ . ":" . str_pad((string) ($anoBase + $offset), 4, "0", STR_PAD_LEFT) . "-01";
                $offset++;
            } else {
                $destino = self::DEST_MATRIZ;
            }

            $usados[$col] = $destino;
            $linhas[] = [
                "indice_coluna"   => $col,
                "header_original" => $header,
                "mapeamento"      => $destino,
            ];
        }

        return $linhas;
    }

    /**
     * Inverte o mapa do banco para o form: destino → índice da coluna.
     *
     * @param array<int,string> $mapa
     * @return array{campos: array<string,int>, periodos_matriz: array<int,int>, ano_base:?int}
     */
    public function expandirMapa(array $mapa): array
    {
        $campos   = [];
        $periodos = [];
        $anos     = [];

        foreach ($mapa as $indice => $destino) {
            $destino = (string) $destino;
            $indice  = (int) $indice;
            if (self::ehDestinoMatriz($destino)) {
                $periodos[] = $indice;
                if (preg_match('/^__matriz__:(\d{4})/', $destino, $m)) {
                    $anos[] = (int) $m[1];
                }
                continue;
            }
            if ($destino !== "") {
                $campos[$destino] = $indice;
            }
        }

        return [
            "campos"          => $campos,
            "periodos_matriz" => $periodos,
            "ano_base"        => $anos ? min($anos) : null,
        ];
    }

    /**
     * Sugestão automática sem IA, a partir dos cabeçalhos.
     *
     * @param array<int,string> $headers
     * @param array<int,object> $contas
     * @param array<int,array<int,string>> $previews
     * @return array{campos: array<string,int>, periodos_matriz: array}
     */
    public function sugerirCampos(array $headers, string $layout, array $contas = [], array $previews = []): array
    {
        return (new DeParaMapper())->sugerir($headers, $layout, $contas, $previews);
    }

    /**
     * @param array<int,string> $headers
     * @param array<int,array<int,string>> $previews
     * @return array<int,array{letra:string,header:string,preview:string,label:string}>
     */
    public function rotulosColunas(array $headers, array $previews = []): array
    {
        $out = [];
        foreach ($headers as $i => $h) {
            $letra   = $this->letra((int) $i);
            $exemplos = !empty($previews[$i]) ? implode(", ", array_slice($previews[$i], 0, 2)) : "";
            $out[(int) $i] = [
                "letra"   => $letra,
                "header"  => (string) $h,
                "preview" => $exemplos,
                "label"   => $letra . " — " . $h . ($exemplos !== "" ? " (" . $exemplos . ")" : ""),
            ];
        }
        return $out;
    }

    /**
     * @return array<int,object>
     */
    public function colunasModeloPorTipo(string $tipo): array
    {
        try {
            $modelo = ModeloDemonstrativo::padraoPorTipo($tipo);
            if (!$modelo) {
                return [];
            }
            return PlanilhaModeloColuna::porModelo((int) $modelo->id);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Campos da esquerda no de-para: o que o demonstrativo precisa.
     *
     * @param array<string,array<int,object>> $contasGrupos
     * @return array{parametrizado:bool,campos:array<int,array{destino:string,label:string,hint:string,grupo:string,busca:string}>}
     */
    public function camposDePara(string $tipo, array $contasGrupos = []): array
    {
        $colunasModelo = $this->colunasModeloPorTipo($tipo);
        $campos        = [];

        foreach ($colunasModelo as $col) {
            $destino = trim((string) ($col->campo_dre ?? ""));
            if ($destino === "") {
                continue;
            }
            $label = trim((string) ($col->descricao ?? $destino));
            $hint  = trim((string) ($col->helper ?? ""));
            $labelNorm = mb_strtolower($label, "UTF-8");
            if (in_array($labelNorm, ["campo padrão", "campo padrao", "padrão", "padrao", "campo"], true)) {
                if (str_starts_with($destino, "conta_")) {
                    continue;
                }
                $label = "";
            }
            $campos[] = [
                "destino" => $destino,
                "label"   => $label !== "" ? $label : $destino,
                "hint"    => $hint,
                "grupo"   => "modelo",
                "busca"   => mb_strtolower($label . " " . $destino, "UTF-8"),
            ];
        }

        $parametrizado = $campos !== [];
        $destinosJa    = array_column($campos, "destino");

        if (!in_array(self::DEST_PERIODO, $destinosJa, true)) {
            array_unshift($campos, [
                "destino" => self::DEST_PERIODO,
                "label"   => "Data / competência",
                "hint"    => "Coluna de data da planilha do cliente",
                "grupo"   => "modelo",
                "busca"   => "data periodo competencia",
            ]);
            $destinosJa[] = self::DEST_PERIODO;
        }
        if (!in_array(self::DEST_DESCRICAO, $destinosJa, true)) {
            $campos[] = [
                "destino" => self::DEST_DESCRICAO,
                "label"   => "Descrição",
                "hint"    => "Produto, título do anúncio ou histórico",
                "grupo"   => "modelo",
                "busca"   => "descricao produto titulo",
            ];
            $destinosJa[] = self::DEST_DESCRICAO;
        }

        foreach ($contasGrupos as $raiz => $contasDoGrupo) {
            foreach ($contasDoGrupo as $conta) {
                $destino = "conta_" . (int) $conta->id;
                if (in_array($destino, $destinosJa, true)) {
                    continue;
                }
                $label = trim((string) $conta->codigo . " — " . (string) $conta->nome);
                $campos[] = [
                    "destino" => $destino,
                    "label"   => $label,
                    "hint"    => "Conta do modelo. Escolha a coluna de valor do cliente, ou deixe vazio.",
                    "grupo"   => (string) $raiz,
                    "busca"   => mb_strtolower($label, "UTF-8"),
                ];
                $destinosJa[] = $destino;
            }
        }

        if (!in_array(self::DEST_UNIDADE, $destinosJa, true)) {
            $campos[] = [
                "destino" => self::DEST_UNIDADE,
                "label"   => "Unidade / Marketplace",
                "hint"    => "Opcional",
                "grupo"   => "modelo",
                "busca"   => "unidade marketplace",
            ];
        }

        $mapper = new DeParaMapper();
        foreach ($campos as $i => $campo) {
            $campos[$i] = $mapper->enriquecerCampo($campo);
        }

        return [
            "parametrizado" => $parametrizado,
            "campos"        => $campos,
        ];
    }

    /**
     * Casa o cabeçalho da planilha com a descrição da Planilha Modelo.
     *
     * @param array<int,string> $headers
     * @param array<int,object> $colunasModelo
     * @return array{campos: array<string,int>, periodos_matriz: array}
     */
    public function sugerirPeloModelo(array $headers, array $colunasModelo): array
    {
        $campos = [];
        $usados = [];

        foreach ($colunasModelo as $col) {
            $destino = trim((string) ($col->campo_dre ?? ""));
            $alvo    = $this->normalizar((string) ($col->descricao ?? ""));
            if ($destino === "" || $alvo === "") {
                continue;
            }
            foreach ($headers as $i => $h) {
                $i = (int) $i;
                if (isset($usados[$i])) {
                    continue;
                }
                if ($this->normalizar((string) $h) === $alvo) {
                    $campos[$destino] = $i;
                    $usados[$i] = true;
                    break;
                }
            }
        }

        return [
            "campos"          => $campos,
            "periodos_matriz" => [],
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function destinosEspeciais(): array
    {
        return [
            self::DEST_PERIODO   => "Período / Data",
            self::DEST_DESCRICAO => "Descrição",
            self::DEST_VALOR     => "Valor",
            self::DEST_UNIDADE   => "Unidade / Centro de custo",
            self::DEST_CONTA     => "Conta (texto na linha)",
        ];
    }

    /**
     * @param array<int,string> $mapa
     * @return array{ok:bool,erro?:string}
     */
    public function validar(array $mapa, string $layout, ?int $idContaPadrao = null): array
    {
        $destinos = array_values($mapa);
        $temPadrao = $idContaPadrao !== null && $idContaPadrao > 0;

        if ($layout === self::LAYOUT_LEDGER) {
            if (!in_array(self::DEST_VALOR, $destinos, true)) {
                return ["ok" => false, "erro" => "Mapeie a coluna do valor (ex.: Total da Shopee)."];
            }
            if (!in_array(self::DEST_CONTA, $destinos, true) && !$temPadrao) {
                return ["ok" => false, "erro" => "Mapeie a coluna com o nome da conta, ou escolha em qual conta do plano lançar o valor."];
            }
            return ["ok" => true];
        }

        if ($layout === self::LAYOUT_MATRIZ) {
            if (!in_array(self::DEST_CONTA, $destinos, true)) {
                return ["ok" => false, "erro" => "No formato matriz, mapeie a coluna com o nome ou código da conta."];
            }
            $temMatriz = false;
            foreach ($destinos as $destino) {
                if (self::ehDestinoMatriz((string) $destino)) {
                    $temMatriz = true;
                    break;
                }
            }
            if (!$temMatriz) {
                return ["ok" => false, "erro" => "Marque ao menos uma coluna de período / valor."];
            }
            return ["ok" => true];
        }

        foreach ($destinos as $destino) {
            if (str_starts_with((string) $destino, "conta_")) {
                return ["ok" => true];
            }
        }

        if (in_array(self::DEST_VALOR, $destinos, true) && $temPadrao) {
            return ["ok" => true];
        }

        return ["ok" => false, "erro" => "Diga de qual coluna vem o valor e em qual conta lançar, ou mapeie as contas do plano."];
    }

    /**
     * @param array<int,string> $mapa
     * @return array{inseridos:int,ignorados:int,avisos:array<int,string>,amostras:array,contas_nao_achadas:array<int,string>}
     */
    public function processar(
        string $caminho,
        int $aba,
        array $mapa,
        int $idProjeto,
        string $tipo,
        int $idUsuario,
        bool $gravar = true,
        ?int $idContaPadrao = null
    ): array {
        $this->gravar   = $gravar;
        $this->amostras = [];

        $layout = $this->layoutDoMapa($mapa, $idContaPadrao);
        $aberto = $this->abrir($caminho);
        $sheet  = $aberto["spreadsheet"]->getSheet($aba);
        $info   = $this->lerCabecalhos($sheet, 0);
        $headers = $info["headers"];
        $highestRow = max(2, $info["highestRow"]);
        $highestCol = $info["highestCol"];
        $primeiraLinha = ((int) ($info["linhaCabecalho"] ?? 1)) + 1;

        $executar = function () use (
            $sheet, $mapa, $headers, $highestRow, $highestCol,
            $idProjeto, $tipo, $aba, $idUsuario, $layout, $idContaPadrao, $primeiraLinha
        ) {
            if ($this->gravar) {
                ProjetoLancamento::limparPorProjeto($idProjeto, $tipo, $aba);
            }

            $indicePorDestino = array_flip($mapa);

            if ($layout === self::LAYOUT_LEDGER) {
                [$inseridos, $ignorados, $naoAcharam, $linhasOrigem] = $this->processarLedger(
                    $sheet, $mapa, $indicePorDestino, $headers, $highestRow, $highestCol,
                    $idProjeto, $tipo, $aba, $idUsuario, $idContaPadrao, $primeiraLinha
                );
            } elseif ($layout === self::LAYOUT_MATRIZ) {
                [$inseridos, $ignorados, $naoAcharam, $linhasOrigem] = $this->processarMatriz(
                    $sheet, $mapa, $indicePorDestino, $headers, $highestRow, $highestCol,
                    $idProjeto, $tipo, $aba, $idUsuario, $primeiraLinha
                );
            } else {
                [$inseridos, $ignorados, $naoAcharam, $linhasOrigem] = $this->processarColunar(
                    $sheet, $mapa, $indicePorDestino, $headers, $highestRow, $highestCol,
                    $idProjeto, $tipo, $aba, $idUsuario, $primeiraLinha
                );
            }

            $avisos = [];
            $nomes  = array_keys($naoAcharam);
            if ($nomes) {
                $mostra = array_slice($nomes, 0, 8);
                $extra  = count($nomes) - count($mostra);
                $aviso  = "Contas da planilha sem correspondência no plano: " . implode(", ", $mostra);
                if ($extra > 0) {
                    $aviso .= " (+{$extra})";
                }
                $avisos[] = $aviso;
            }

            return [
                "inseridos"           => $inseridos,
                "linhas_origem"       => $linhasOrigem,
                "ignorados"           => $ignorados,
                "avisos"              => $avisos,
                "amostras"            => $this->amostras,
                "contas_nao_achadas"  => $nomes,
            ];
        };

        return $this->gravar ? DB::transaction($executar) : $executar();
    }

    public function celula(Worksheet $sheet, int $row, int $col, bool $isoDate = true): string
    {
        $letra = Coordinate::stringFromColumnIndex($col);
        $cell  = $sheet->getCell($letra . $row);
        $raw   = $cell->getValue();

        if (is_float($raw) || is_int($raw)) {
            $fmt = $cell->getStyle()->getNumberFormat()->getFormatCode();
            if (ExcelDate::isDateTimeFormatCode($fmt)) {
                $ts = ExcelDate::excelToTimestamp((float) $raw);
                return $isoDate ? date("Y-m-d", $ts) : date("d/m/Y", $ts);
            }
            return (string) $raw;
        }

        $val = $cell->getCalculatedValue();
        return trim(html_entity_decode((string) $val, ENT_QUOTES | ENT_HTML5, "UTF-8"));
    }

    /**
     * @return array<int,string> índice 1-based
     */
    public function linha(Worksheet $sheet, int $row, int $highestCol): array
    {
        $out = [];
        for ($c = 1; $c <= $highestCol; $c++) {
            $out[$c] = $this->celula($sheet, $row, $c, true);
        }
        return $out;
    }

    public function parsePeriodo(?string $raw, string $fallbackHeader = "", ?int $anoBase = null): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === "") {
            $raw = trim($fallbackHeader);
        }
        if ($raw === "") {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            return $m[1] . "-" . $m[2] . "-" . $m[3];
        }

        $meses = $this->mapaMeses();

        // Mercado Livre: "31 de março de 2024" / "31 de marzo de 2024"
        if (preg_match('/(\d{1,2})\s+de\s+([a-zà-úç]+)\s+(?:de\s+)?(\d{4})/iu', $raw, $m)) {
            $mes = $this->numeroMes((string) $m[2], $meses);
            if ($mes) {
                return $m[3] . "-" . $mes . "-" . str_pad($m[1], 2, "0", STR_PAD_LEFT);
            }
        }

        if (preg_match('/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/', $raw, $m)) {
            $ano = strlen($m[3]) === 2 ? "20" . $m[3] : $m[3];
            $dia = (int) $m[1];
            $mes = (int) $m[2];
            if ($mes > 12 && $dia <= 12) {
                [$dia, $mes] = [$mes, $dia];
            }
            if ($mes >= 1 && $mes <= 12 && $dia >= 1 && $dia <= 31) {
                return sprintf("%s-%02d-%02d", $ano, $mes, $dia);
            }
        }

        if (preg_match('/^(\d{1,2})[\/\-.](\d{2,4})$/', $raw, $m)) {
            $mes = str_pad($m[1], 2, "0", STR_PAD_LEFT);
            $ano = strlen($m[2]) === 2 ? "20" . $m[2] : $m[2];
            return "{$ano}-{$mes}-01";
        }

        if (preg_match('/^(\d{4})[\/\-.](\d{1,2})$/', $raw, $m)) {
            $mes = str_pad($m[2], 2, "0", STR_PAD_LEFT);
            return "{$m[1]}-{$mes}-01";
        }

        $n = $this->normalizar($raw);
        foreach ($meses as $nome => $num) {
            if ($nome === "" || !str_contains($n, $nome)) {
                continue;
            }
            $ano = null;
            if (preg_match('/(20\d{2})/', $raw, $am)) {
                $ano = $am[1];
            } elseif (preg_match('/(\d{2})$/', $raw, $am)) {
                $ano = "20" . $am[1];
            } elseif ($anoBase) {
                $ano = (string) $anoBase;
            }
            if (!$ano) {
                return null;
            }
            $dia = "01";
            if (preg_match('/^(\d{1,2})\D/', $raw, $dm)) {
                $dia = str_pad($dm[1], 2, "0", STR_PAD_LEFT);
            }
            return "{$ano}-{$num}-{$dia}";
        }

        if (preg_match('/^(20\d{2})(?:\D+(\d{1,2}))?/', $raw, $m)) {
            $mes = isset($m[2]) && $m[2] !== "" ? str_pad($m[2], 2, "0", STR_PAD_LEFT) : "01";
            return "{$m[1]}-{$mes}-01";
        }

        if (is_numeric($raw) && (float) $raw > 20000) {
            try {
                $ts = ExcelDate::excelToTimestamp((int) $raw);
                return date("Y-m-d", $ts);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return array<string,string>
     */
    private function mapaMeses(): array
    {
        return [
            "janeiro" => "01", "enero" => "01", "january" => "01", "jan" => "01",
            "fevereiro" => "02", "febrero" => "02", "february" => "02", "fev" => "02", "feb" => "02",
            "marco" => "03", "marzo" => "03", "march" => "03", "mar" => "03",
            "abril" => "04", "april" => "04", "abr" => "04", "apr" => "04",
            "maio" => "05", "mayo" => "05", "may" => "05", "mai" => "05",
            "junho" => "06", "junio" => "06", "june" => "06", "jun" => "06",
            "julho" => "07", "julio" => "07", "july" => "07", "jul" => "07",
            "agosto" => "08", "august" => "08", "ago" => "08", "aug" => "08",
            "setembro" => "09", "septiembre" => "09", "setiembre" => "09", "september" => "09", "set" => "09", "sep" => "09",
            "outubro" => "10", "octubre" => "10", "october" => "10", "out" => "10", "oct" => "10",
            "novembro" => "11", "noviembre" => "11", "november" => "11", "nov" => "11",
            "dezembro" => "12", "diciembre" => "12", "december" => "12", "dez" => "12", "dic" => "12", "dec" => "12",
        ];
    }

    /**
     * @param array<string,string> $meses
     */
    private function numeroMes(string $nome, array $meses): ?string
    {
        $n = $this->normalizar($nome);
        if (isset($meses[$n])) {
            return $meses[$n];
        }
        foreach ($meses as $chave => $num) {
            if (str_starts_with($n, $chave) || str_starts_with($chave, $n)) {
                return $num;
            }
        }
        return null;
    }

    public function parseValor(?string $raw): ?float
    {
        $raw = trim((string) $raw);
        if ($raw === "" || $raw === "-" || $raw === "—" || strcasecmp($raw, "null") === 0) {
            return null;
        }

        $negativo = false;
        if (preg_match('/^\((.+)\)$/', $raw, $m)) {
            $raw = $m[1];
            $negativo = true;
        }
        if (preg_match('/^[\-\x{2212}\x{2013}\x{2014}]/u', $raw)) {
            $negativo = true;
            $raw = preg_replace('/^[\-\x{2212}\x{2013}\x{2014}\s]+/u', '', $raw) ?? $raw;
        }

        $raw = str_replace(["R$", " ", "\xc2\xa0"], "", $raw);

        if (preg_match('/^\d{1,3}(\.\d{3})+,\d+$/', $raw)) {
            $raw = str_replace(".", "", $raw);
            $raw = str_replace(",", ".", $raw);
        } elseif (str_contains($raw, ",") && !str_contains($raw, ".")) {
            $raw = str_replace(",", ".", $raw);
        } else {
            $raw = str_replace(",", "", $raw);
        }

        if (!is_numeric($raw)) {
            return null;
        }

        $valor = (float) $raw;
        if ($negativo) {
            $valor *= -1;
        }
        if ($valor == 0.0) {
            return null;
        }
        if (abs($valor) >= 100000000000) {
            return null;
        }

        return round($valor, 2);
    }

    public function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), "UTF-8");
        $ascii = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $texto);
        $texto = is_string($ascii) ? $ascii : $texto;
        return (string) preg_replace("/[^a-z0-9]+/", "", $texto);
    }

    public function letra(int $indiceZero): string
    {
        return Coordinate::stringFromColumnIndex($indiceZero + 1);
    }

    /**
     * @param array<int,string> $mapa
     * @param array<string,int> $indicePorDestino
     * @param array<int,string> $headers
     * @return array{0:int,1:int,2:array<string,bool>}
     */
    private function processarLedger(
        Worksheet $sheet,
        array $mapa,
        array $indicePorDestino,
        array $headers,
        int $highestRow,
        int $highestCol,
        int $idProjeto,
        string $tipo,
        int $aba,
        int $idUsuario,
        ?int $idContaPadrao = null,
        int $primeiraLinha = 2
    ): array {
        $colConta   = isset($indicePorDestino[self::DEST_CONTA]) ? (int) $indicePorDestino[self::DEST_CONTA] : null;
        $colValor   = isset($indicePorDestino[self::DEST_VALOR]) ? (int) $indicePorDestino[self::DEST_VALOR] : null;
        $colPeriodo = isset($indicePorDestino[self::DEST_PERIODO]) ? (int) $indicePorDestino[self::DEST_PERIODO] : null;
        $colDesc    = isset($indicePorDestino[self::DEST_DESCRICAO]) ? (int) $indicePorDestino[self::DEST_DESCRICAO] : null;
        $colUnid    = isset($indicePorDestino[self::DEST_UNIDADE]) ? (int) $indicePorDestino[self::DEST_UNIDADE] : null;

        $contaFixa = null;
        if ($idContaPadrao) {
            $contaFixa = DreConta::find($idContaPadrao);
            if ($contaFixa && ((string) ($contaFixa->tipo ?? "") !== "analitica" || (int) ($contaFixa->trash ?? 0) === 1)) {
                $contaFixa = null;
            }
        }

        $inseridos  = 0;
        $ignorados  = 0;
        $naoAcharam = [];

        for ($r = $primeiraLinha; $r <= $highestRow; $r++) {
            $linha = $this->linha($sheet, $r, $highestCol);
            if ($this->linhaVazia($linha)) {
                continue;
            }

            $valor = $this->parseValor((string) $this->celulaDaLinha($linha, $colValor));
            if ($valor === null) {
                $ignorados++;
                continue;
            }

            $nomeConta = $colConta !== null
                ? trim((string) $this->celulaDaLinha($linha, $colConta))
                : "";

            if ($colConta !== null) {
                if ($nomeConta === "" || $this->pareceLinhaResumo($nomeConta)) {
                    $ignorados++;
                    continue;
                }
                $conta = DreConta::buscarAnaliticaPorTexto($tipo, $nomeConta);
                if (!$conta) {
                    $naoAcharam[$nomeConta] = true;
                    $ignorados++;
                    continue;
                }
            } else {
                $conta = $contaFixa;
                if (!$conta) {
                    $ignorados++;
                    continue;
                }
            }

            $periodo = $colPeriodo !== null
                ? $this->parsePeriodo((string) $this->celulaDaLinha($linha, $colPeriodo), (string) ($headers[$colPeriodo] ?? ""))
                : null;

            $rotulo = trim((string) ($conta->codigo ?? "") . " — " . (string) ($conta->nome ?? $nomeConta));

            $this->gravarLancamento(
                $idProjeto,
                $tipo,
                $aba,
                (int) $conta->id,
                $periodo,
                $colDesc !== null ? $this->textoOuNulo($this->celulaDaLinha($linha, $colDesc)) : null,
                $valor,
                $colUnid !== null ? $this->textoOuNulo($this->celulaDaLinha($linha, $colUnid)) : null,
                $r,
                $colConta !== null ? self::DEST_CONTA : self::DEST_VALOR,
                $idUsuario,
                $rotulo
            );
            $inseridos++;
        }

        return [$inseridos, $ignorados, $naoAcharam, $inseridos];
    }

    /**
     * @param array<int,string> $mapa
     * @param array<string,int> $indicePorDestino
     * @param array<int,string> $headers
     * @return array{0:int,1:int,2:array<string,bool>}
     */
    private function processarMatriz(
        Worksheet $sheet,
        array $mapa,
        array $indicePorDestino,
        array $headers,
        int $highestRow,
        int $highestCol,
        int $idProjeto,
        string $tipo,
        int $aba,
        int $idUsuario,
        int $primeiraLinha = 2
    ): array {
        $colConta  = isset($indicePorDestino[self::DEST_CONTA]) ? (int) $indicePorDestino[self::DEST_CONTA] : null;
        $colsValor = [];
        foreach ($mapa as $indice => $destino) {
            if (self::ehDestinoMatriz((string) $destino)) {
                $colsValor[] = [(int) $indice, (string) $destino];
            }
        }

        $inseridos  = 0;
        $ignorados  = 0;
        $naoAcharam = [];
        $linhasOrigem = 0;

        for ($r = $primeiraLinha; $r <= $highestRow; $r++) {
            $linha = $this->linha($sheet, $r, $highestCol);
            if ($this->linhaVazia($linha)) {
                continue;
            }

            $nomeConta = trim((string) $this->celulaDaLinha($linha, $colConta));
            if ($nomeConta === "" || $this->pareceLinhaResumo($nomeConta)) {
                $ignorados++;
                continue;
            }

            $conta = DreConta::buscarAnaliticaPorTexto($tipo, $nomeConta);
            if (!$conta) {
                $naoAcharam[$nomeConta] = true;
                $ignorados++;
                continue;
            }

            $rotulo = trim((string) ($conta->codigo ?? "") . " — " . (string) ($conta->nome ?? $nomeConta));
            $criou  = false;
            foreach ($colsValor as [$colIdx, $destino]) {
                $valor = $this->parseValor((string) $this->celulaDaLinha($linha, $colIdx));
                if ($valor === null) {
                    continue;
                }
                $cabecalho = (string) ($headers[$colIdx] ?? "");
                $periodo   = $this->periodoDoDestinoMatriz($destino, $cabecalho);
                $this->gravarLancamento(
                    $idProjeto,
                    $tipo,
                    $aba,
                    (int) $conta->id,
                    $periodo,
                    $cabecalho !== "" ? $cabecalho : null,
                    $valor,
                    null,
                    $r,
                    $destino,
                    $idUsuario,
                    $rotulo
                );
                $inseridos++;
                $criou = true;
            }
            if (!$criou) {
                $ignorados++;
            } else {
                $linhasOrigem++;
            }
        }

        return [$inseridos, $ignorados, $naoAcharam, $linhasOrigem];
    }

    /**
     * @param array<int,string> $mapa
     * @param array<string,int> $indicePorDestino
     * @param array<int,string> $headers
     * @return array{0:int,1:int,2:array<string,bool>}
     */
    private function processarColunar(
        Worksheet $sheet,
        array $mapa,
        array $indicePorDestino,
        array $headers,
        int $highestRow,
        int $highestCol,
        int $idProjeto,
        string $tipo,
        int $aba,
        int $idUsuario,
        int $primeiraLinha = 2
    ): array {
        $colPeriodo = isset($indicePorDestino[self::DEST_PERIODO]) ? (int) $indicePorDestino[self::DEST_PERIODO] : null;
        $colDesc    = isset($indicePorDestino[self::DEST_DESCRICAO]) ? (int) $indicePorDestino[self::DEST_DESCRICAO] : null;
        $colUnid    = isset($indicePorDestino[self::DEST_UNIDADE]) ? (int) $indicePorDestino[self::DEST_UNIDADE] : null;

        $inseridos  = 0;
        $ignorados  = 0;
        $naoAcharam = [];
        $linhasOrigem = 0;

        for ($r = $primeiraLinha; $r <= $highestRow; $r++) {
            $linha = $this->linha($sheet, $r, $highestCol);
            if ($this->linhaVazia($linha)) {
                continue;
            }

            $periodo = $colPeriodo !== null
                ? $this->parsePeriodo((string) $this->celulaDaLinha($linha, $colPeriodo), (string) ($headers[$colPeriodo] ?? ""))
                : null;
            $descricao = $colDesc !== null ? $this->textoOuNulo($this->celulaDaLinha($linha, $colDesc)) : null;
            $unidade   = $colUnid !== null ? $this->textoOuNulo($this->celulaDaLinha($linha, $colUnid)) : null;

            $criou = false;
            foreach ($mapa as $colIdx => $destino) {
                if (!str_starts_with((string) $destino, "conta_")) {
                    continue;
                }
                $idConta = (int) substr((string) $destino, 6);
                if ($idConta <= 0) {
                    continue;
                }
                $valor = $this->parseValor((string) $this->celulaDaLinha($linha, (int) $colIdx));
                if ($valor === null) {
                    continue;
                }

                $contaObj = DreConta::find($idConta);
                $rotulo   = $contaObj
                    ? trim((string) ($contaObj->codigo ?? "") . " — " . (string) ($contaObj->nome ?? ""))
                    : "conta_" . $idConta;
                $origem = (string) ($headers[(int) $colIdx] ?? "");

                $this->gravarLancamento(
                    $idProjeto,
                    $tipo,
                    $aba,
                    $idConta,
                    $periodo,
                    $descricao,
                    $valor,
                    $unidade,
                    $r,
                    (string) $destino,
                    $idUsuario,
                    $rotulo,
                    $origem
                );
                $inseridos++;
                $criou = true;
            }

            if (!$criou) {
                $ignorados++;
            } else {
                $linhasOrigem++;
            }
        }

        return [$inseridos, $ignorados, $naoAcharam, $linhasOrigem];
    }

    private function celulaDaLinha(array $linha, ?int $indiceZero): string
    {
        if ($indiceZero === null) {
            return "";
        }
        return (string) ($linha[$indiceZero + 1] ?? "");
    }

    /**
     * @param array<int,object> $contas
     */
    private function casarContaPorHeader(string $header, array $contas): ?object
    {
        return (new DeParaMapper())->casarConta($header, $contas);
    }

    private function aliasParaConta(string $n): ?string
    {
        return (new DeParaMapper())->aliasParaConta($n);
    }

    private function periodoDoDestinoMatriz(string $destino, string $header): ?string
    {
        if (preg_match('/^__matriz__:(\d{4}-\d{2})(?:-\d{2})?$/', $destino, $m)) {
            return $m[1] . "-01";
        }
        return $this->parsePeriodo($header, $header);
    }

    private function pareceLinhaResumo(string $nome): bool
    {
        $n = $this->normalizar($nome);
        return in_array($n, ["total", "totais", "subtotal", "soma", "somageral"], true);
    }

    private function gravarLancamento(
        int $idProjeto,
        string $tipo,
        int $aba,
        int $idConta,
        ?string $periodo,
        ?string $descricao,
        float $valor,
        ?string $unidade,
        int $linha,
        string $mapeamento,
        int $idUsuario,
        string $rotuloConta = "",
        string $origemColuna = ""
    ): void {
        if (count($this->amostras) < 25) {
            $this->amostras[] = [
                "linha"   => $linha,
                "origem"  => $origemColuna,
                "conta"   => $rotuloConta !== "" ? $rotuloConta : $mapeamento,
                "periodo" => $periodo,
                "valor"   => $valor,
            ];
        }

        if (!$this->gravar) {
            return;
        }

        ProjetoLancamento::create([
            "id_projeto"         => $idProjeto,
            "tipo_demonstrativo" => $tipo,
            "aba"                => $aba,
            "id_dre_conta"       => $idConta,
            "periodo"            => $periodo,
            "descricao"          => $descricao,
            "valor"              => $valor,
            "unidade"            => $unidade,
            "linha"              => $linha,
            "mapeamento"         => $mapeamento,
            "created_by"         => $idUsuario,
        ]);
    }

    private function linhaVazia(array $linha): bool
    {
        foreach ($linha as $valor) {
            if (trim((string) $valor) !== "") {
                return false;
            }
        }
        return true;
    }

    private function textoOuNulo($valor): ?string
    {
        $valor = trim((string) $valor);
        return $valor === "" ? null : $valor;
    }

    private function parecePeriodo(string $n): bool
    {
        return in_array($n, [
            "periodo", "competencia", "data", "datavenda", "datadavenda", "mes", "ano",
            "fecha", "fechaventa", "fechadeventa", "date", "orderdate",
        ], true)
            || str_contains($n, "periodo")
            || str_contains($n, "competencia")
            || str_contains($n, "datavenda")
            || str_contains($n, "fechadeventa")
            || str_contains($n, "fechaventa");
    }

    private function pareceConta(string $n): bool
    {
        return in_array($n, [
            "conta", "contaplano", "nomedaconta", "descricaodaconta",
            "classificacao", "rubrica",
        ], true) || (str_starts_with($n, "conta") && !str_contains($n, "contato"));
    }

    private function pareceValor(string $n): bool
    {
        return in_array($n, [
            "valor", "valortotal", "vlr", "amount", "valorrs", "total", "preco", "receita",
            "ingresos", "ingresosporproducto", "receitaporproduto", "preciounitario",
        ], true)
            || str_contains($n, "valortotal")
            || str_contains($n, "receitapor")
            || str_contains($n, "ingresospor")
            || ($n === "ingreso");
    }

    private function pareceDescricao(string $n): bool
    {
        return (new DeParaMapper())->pareceDescricao($n);
    }

    private function pareceUnidade(string $n): bool
    {
        return in_array($n, ["unidade", "centrocusto", "cc", "filial", "loja", "marketplace", "canal", "empresa"], true);
    }

    private function pareceCabecalhoPeriodo(string $n): bool
    {
        if (preg_match("/^(ano\d+|20\d{2}|jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)/", $n)) {
            return true;
        }
        return false;
    }
}
