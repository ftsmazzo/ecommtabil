<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\DB;
use App\Lib\ChatGPT;
use App\Models\DreConta;
use App\Models\TipoDemonstrativo;
use App\Models\Empresa;
use App\Models\Projeto;
use App\Models\ProjetoLancamento;
use App\Models\ProjetoMapeamentoColuna;
use App\Models\UsuarioProjetoRecente;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use App\Services\MenuService;

class ProjetoController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title"       => "Projetos",
            "active_menu" => "projetos",
        ]);
    }

    public function index(): void
    {
        $this->authorize("projeto_gerenciar");

        $this->view->addData([
            "breadcrumb" => [
                "Projetos" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Projetos",
                "desc"  => "Gerencie os projetos vinculados às empresas",
            ],
        ]);

        $projetos = Projeto::leftJoin("empresa as e", "p.id_empresa", "=", "e.id")
            ->select("p.*", "e.razao as empresa_razao", "e.nome as empresa_nome")
            ->orderBy("p.nome")
            ->get();

        foreach ($projetos as $projeto) {
            $projeto->empresa_print = trim((string) ($projeto->empresa_razao ?: $projeto->empresa_nome)) ?: "-";
            $projeto->abrir_url     = $this->router->route("admin.projeto.abrir", ["id" => $projeto->id]);
            $projeto->disabled      = $this->auth->allow("projeto_excluir") ? "" : "disabled";
            $projeto->action        = $this->auth->allow("projeto_excluir")
                ? 'onclick="Delete(\'projetos/delete\', \'' . $projeto->id . '\')"'
                : "";
            $projeto->title = $this->auth->allow("projeto_excluir") ? "Excluir projeto" : "Sem permissão";
        }

        $permissao = [
            "inserir" => $this->auth->allow("projeto_inserir"),
            "editar"  => $this->auth->allow("projeto_editar"),
            "excluir" => $this->auth->allow("projeto_excluir"),
        ];

        echo $this->view->render("admin/projeto/lista", [
            "dados"     => $projetos,
            "permissao" => $permissao,
            "empresas"  => Empresa::orderBy("razao")->get(),
            "csrf"      => $this->csrf->generate(),
        ]);
    }

    public function new(): void
    {
        $this->authorize("projeto_inserir");

        $this->view->addData([
            "breadcrumb" => [
                "Projetos"     => ["url" => $this->router->route("admin.projeto.index"), "current" => false],
                "Novo Projeto" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Novo Projeto",
                "desc"  => "Vincule um projeto a uma empresa",
            ],
        ]);

        echo $this->view->render("admin/projeto/novo", [
            "csrf"     => $this->csrf->generate(),
            "empresas" => Empresa::orderBy("razao")->get(),
            "url_back" => $this->router->route("admin.projeto.index"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("projeto_inserir");

        $data = new Data($request->all());

        $payload = [
            "id_empresa" => (int) ($data->id_empresa ?? 0) ?: null,
            "nome"       => trim((string) ($data->nome ?? "")),
            "descricao"  => trim((string) ($data->descricao ?? "")) ?: null,
            "trash"      => 0,
            "created_by" => $this->user->uid,
        ];

        $projeto = Projeto::create($payload);

        $this->message->success("Projeto cadastrado com sucesso");
        $this->router->redirect("admin.projeto.abrir", ["id" => $projeto->id]);
    }

    public function edit(Request $request): void
    {
        $this->authorize("projeto_editar");

        $data    = new Data($request->all());
        $projeto = Projeto::find($data->id);

        if (!$projeto) {
            $this->message->warning("Projeto não encontrado");
            $this->router->redirect("admin.projeto.index");
            return;
        }

        $this->view->addData([
            "breadcrumb" => [
                "Projetos"       => ["url" => $this->router->route("admin.projeto.index"), "current" => false],
                "Editar Projeto" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Editar Projeto",
                "desc"  => "Atualize os dados do projeto",
            ],
        ]);

        echo $this->view->render("admin/projeto/editar", [
            "csrf"     => $this->csrf->generate(),
            "projeto"  => $projeto,
            "empresas" => Empresa::orderBy("razao")->get(),
            "url_back" => $this->router->route("admin.projeto.index"),
        ]);
    }

    public function update(Request $request): void
    {
        $this->authorize("projeto_editar");

        $data    = new Data($request->all());
        $projeto = Projeto::find($data->id);

        if (!$projeto) {
            $this->message->warning("Projeto não encontrado");
            Redirect::referer();
            return;
        }

        $payload = [
            "id_empresa" => (int) ($data->id_empresa ?? 0) ?: null,
            "nome"       => trim((string) ($data->nome ?? "")),
            "descricao"  => trim((string) ($data->descricao ?? "")) ?: null,
            "updated_by" => $this->user->uid,
        ];

        Projeto::updateBy($projeto->id, $payload);

        $this->message->success("Projeto atualizado com sucesso");
        $this->router->redirect("admin.projeto.abrir", ["id" => $projeto->id]);
    }

    public function delete(Request $request): void
    {
        $this->authorize("projeto_excluir");

        $data    = new Data($request->all());
        $projeto = Projeto::find($data->id);

        if (!$projeto) {
            $this->message->warning("Projeto não encontrado");
            Redirect::referer();
            return;
        }

        Projeto::updateBy($projeto->id, [
            "trash"      => 1,
            "deleted_by" => $this->user->uid,
            "deleted_at" => date("Y-m-d H:i:s"),
        ]);

        $this->message->success("Projeto removido com sucesso");
        Redirect::referer();
    }

    public function open(Request $request): void
    {
        $this->authorize("projeto_gerenciar");

        $data    = new Data($request->all());
        $projeto = Projeto::leftJoin("empresa as e", "p.id_empresa", "=", "e.id")
            ->select("p.*", "e.razao as empresa_razao", "e.nome as empresa_nome")
            ->where("p.id", "=", (int) ($data->id ?? 0))
            ->first();

        if (!$projeto) {
            $this->message->warning("Projeto não encontrado");
            $this->router->redirect("admin.projeto.index");
            return;
        }

        $projeto->empresa_print = trim((string) ($projeto->empresa_razao ?: $projeto->empresa_nome)) ?: "-";
        $this->rememberVisitedProject($projeto);

        $projetoLabel = trim($projeto->nome ?? "") ?: $projeto->empresa_print;

        $tipos = TipoDemonstrativo::options();
        $resumos = [];
        foreach (array_keys($tipos) as $slug) {
            $resumos[$slug] = ProjetoLancamento::resumoPorProjeto((int) $projeto->id, $slug);
        }

        $this->view->addData([
            "breadcrumb" => [
                "Projetos"    => ["url" => $this->router->route("admin.projeto.index"), "current" => false],
                $projetoLabel => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => $projetoLabel,
                "desc"  => $projeto->empresa_print,
            ],
            "title" => $projetoLabel,
        ]);

        echo $this->view->render("admin/projeto/abrir", [
            "projeto"   => $projeto,
            "aba"       => "visao-geral",
            "csrf"      => $this->csrf->generate(),
            "empresas"  => Empresa::orderBy("razao")->get(),
            "tipos"     => $tipos,
            "resumos"   => $resumos,
            "permissao" => [
                "editar"  => $this->auth->allow("projeto_editar"),
                "excluir" => $this->auth->allow("projeto_excluir"),
            ],
        ]);
    }

    public function importacao(Request $request): void
    {
        $this->authorize("projeto_gerenciar");

        $data    = new Data($request->all());
        $projeto = Projeto::leftJoin("empresa as e", "p.id_empresa", "=", "e.id")
            ->select("p.*", "e.razao as empresa_razao", "e.nome as empresa_nome")
            ->where("p.id", "=", (int) ($data->id ?? 0))
            ->first();

        if (!$projeto) {
            $this->message->warning("Projeto não encontrado");
            $this->router->redirect("admin.projeto.index");
            return;
        }

        $projeto->empresa_print = trim((string) ($projeto->empresa_razao ?: $projeto->empresa_nome)) ?: "-";
        $this->rememberVisitedProject($projeto);

        $projetoLabel = trim($projeto->nome ?? "") ?: $projeto->empresa_print;

        $this->view->addData([
            "breadcrumb" => [
                "Projetos"    => ["url" => $this->router->route("admin.projeto.index"), "current" => false],
                $projetoLabel => ["url" => $this->router->route("admin.projeto.abrir", ["id" => $projeto->id]), "current" => false],
                "Importação"  => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => $projeto->nome,
                "desc"  => $projeto->empresa_print,
            ],
            "title" => $projeto->nome,
        ]);

        $tipoDemo = TipoDemonstrativo::existeSigla($data->tipo ?? "")
            ? $data->tipo
            : TipoDemonstrativo::padrao()?->sigla;

        echo $this->view->render("admin/projeto/importacao", [
            "projeto"    => $projeto,
            "aba"        => "importacao",
            "tipo"       => $tipoDemo,
            "tipos"      => TipoDemonstrativo::options(),
            "csrf"       => $this->csrf->generate(),
            "empresas"   => Empresa::orderBy("razao")->get(),
            "permissao"  => [
                "editar"  => $this->auth->allow("projeto_editar"),
                "excluir" => $this->auth->allow("projeto_excluir"),
            ],
        ]);
    }

    public function uploadPlanilha(Request $request): void
    {
        $this->authorize("projeto_gerenciar");

        $data    = new Data($request->all());
        $projeto = Projeto::find((int) ($data->id ?? 0));

        if (!$projeto) {
            $this->message->warning("Projeto não encontrado");
            $this->router->redirect("admin.projeto.index");
            return;
        }

        $file = $_FILES["arquivo"] ?? null;

        if (!$file || $file["error"] !== UPLOAD_ERR_OK) {
            $this->message->warning("Nenhum arquivo enviado ou erro no upload");
            $this->router->redirect("admin.projeto.importacao", ["id" => $projeto->id]);
            return;
        }

        $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        if (!in_array($ext, ["xlsx", "xls", "csv"])) {
            $this->message->warning("Formato inválido. Envie um arquivo XLSX, XLS ou CSV");
            $this->router->redirect("admin.projeto.importacao", ["id" => $projeto->id]);
            return;
        }

        $dir = PATH_ROOT . "/storage/tmp/planilhas/";
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $nome    = "proj_{$projeto->id}_" . time() . "_" . uniqid() . "." . $ext;
        $destino = $dir . $nome;

        if (!move_uploaded_file($file["tmp_name"], $destino)) {
            $this->message->error("Falha ao salvar o arquivo. Tente novamente");
            $this->router->redirect("admin.projeto.importacao", ["id" => $projeto->id]);
            return;
        }

        $tipoDemo = TipoDemonstrativo::existeSigla($data->tipo_demonstrativo ?? "")
            ? $data->tipo_demonstrativo
            : TipoDemonstrativo::padrao()?->sigla;

        $this->session->set("planilha_upload", [
            "arquivo"  => $nome,
            "original" => $file["name"],
            "tipo"     => $tipoDemo,
            "projeto"  => $projeto->id,
        ]);

        $this->router->redirect("admin.projeto.importacao.mapear", ["id" => $projeto->id]);
    }

    public function mapeamento(Request $request): void
    {
        $this->authorize("projeto_gerenciar");

        $data    = new Data($request->all());
        $projeto = Projeto::leftJoin("empresa as e", "p.id_empresa", "=", "e.id")
            ->select("p.*", "e.razao as empresa_razao", "e.nome as empresa_nome")
            ->where("p.id", "=", (int) ($data->id ?? 0))
            ->first();

        if (!$projeto) {
            $this->message->warning("Projeto não encontrado");
            $this->router->redirect("admin.projeto.index");
            return;
        }

        $upload = $this->session->get("planilha_upload");

        if (!$upload || ($upload->projeto ?? null) != $projeto->id) {
            $this->message->warning("Nenhum arquivo em processamento. Faça o upload novamente");
            $this->router->redirect("admin.projeto.importacao", ["id" => $projeto->id]);
            return;
        }

        $caminho = PATH_ROOT . "/storage/tmp/planilhas/" . $upload->arquivo;

        if (!file_exists($caminho)) {
            $this->message->warning("Arquivo não encontrado. Faça o upload novamente");
            $this->session->unset("planilha_upload");
            $this->router->redirect("admin.projeto.importacao", ["id" => $projeto->id]);
            return;
        }

        try {
            $reader      = IOFactory::createReaderForFile($caminho);
            $spreadsheet = $reader->load($caminho);
            $sheetNames  = $spreadsheet->getSheetNames();

            // Aba ativa: querystring ou primeira da lista
            $abaAtiva = (int) ($data->aba ?? 0);
            if ($abaAtiva < 0 || $abaAtiva >= count($sheetNames)) $abaAtiva = 0;

            $sheet   = $spreadsheet->getSheet($abaAtiva);
            $maxCol  = $sheet->getHighestDataColumn();
            $maxColN = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCol);

            $headers  = [];
            $previews = [];

            for ($c = 1; $c <= $maxColN; $c++) {
                $letra  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                $header = trim(html_entity_decode(
                    (string) $sheet->getCell($letra . "1")->getCalculatedValue(),
                    ENT_QUOTES | ENT_HTML5, "UTF-8"
                ));

                $preview = [];
                for ($r = 2; $r <= min(4, $sheet->getHighestDataRow()); $r++) {
                    $cell = $sheet->getCell($letra . $r);
                    $raw  = $cell->getValue();

                    if (is_float($raw) || is_int($raw)) {
                        $fmt = $cell->getStyle()->getNumberFormat()->getFormatCode();
                        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTimeFormatCode($fmt)) {
                            $ts  = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($raw);
                            $val = date("d/m/Y", $ts);
                        } else {
                            $val = (string) $raw;
                        }
                    } else {
                        $val = html_entity_decode(
                            (string) $cell->getCalculatedValue(),
                            ENT_QUOTES | ENT_HTML5, "UTF-8"
                        );
                    }

                    $val = trim($val);
                    if ($val !== "") $preview[] = $val;
                }

                $headers[]  = $header !== "" ? $header : "Coluna " . $letra;
                $previews[] = $preview;
            }

        } catch (\Throwable $e) {
            $this->message->error("Não foi possível ler o arquivo: " . $e->getMessage());
            $this->router->redirect("admin.projeto.importacao", ["id" => $projeto->id]);
            return;
        }

        $projeto->empresa_print = trim((string) ($projeto->empresa_razao ?: $projeto->empresa_nome)) ?: "-";
        $this->rememberVisitedProject($projeto);
        $projetoLabel = trim($projeto->nome ?? "") ?: $projeto->empresa_print;

        $this->view->addData([
            "breadcrumb" => [
                "Projetos"            => ["url" => $this->router->route("admin.projeto.index"), "current" => false],
                $projetoLabel         => ["url" => $this->router->route("admin.projeto.abrir", ["id" => $projeto->id]), "current" => false],
                "Importação"          => ["url" => $this->router->route("admin.projeto.importacao", ["id" => $projeto->id]), "current" => false],
                "Mapeamento de Colunas" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Mapeamento de Colunas",
                "desc"  => $projetoLabel . " — " . strtoupper($upload->tipo),
            ],
        ]);

        $mapeamentoSalvo = ProjetoMapeamentoColuna::porProjeto($projeto->id, $upload->tipo, $abaAtiva);
        $lancamentosExistentes = ProjetoLancamento::resumoPorProjeto($projeto->id, $upload->tipo);

        echo $this->view->render("admin/projeto/mapeamento", [
            "projeto"               => $projeto,
            "upload"                => $upload,
            "sheetNames"            => $sheetNames,
            "abaAtiva"              => $abaAtiva,
            "headers"               => $headers,
            "previews"              => $previews,
            "contas"                => DreConta::analiticasPorTipo($upload->tipo),
            "tipos"                 => TipoDemonstrativo::options(),
            "mapeamentoSalvo"       => $mapeamentoSalvo,
            "lancamentosExistentes" => $lancamentosExistentes,
            "csrf"                  => $this->csrf->generate(),
        ]);
    }

    public function mapeamentoPreview(Request $request): void
    {
        $this->authorize("projeto_gerenciar");

        $data    = new Data($request->all());
        $upload  = $this->session->get("planilha_upload");

        if (!$upload || ($upload->projeto ?? null) != (int) ($data->id ?? 0)) {
            echo json_encode(["error" => true, "message" => "Sessão expirada"]);
            return;
        }

        $caminho = PATH_ROOT . "/storage/tmp/planilhas/" . $upload->arquivo;
        $abaIdx  = (int) ($data->aba ?? 0);

        try {
            $reader      = IOFactory::createReaderForFile($caminho);
            $spreadsheet = $reader->load($caminho);
            $sheet       = $spreadsheet->getSheet($abaIdx);
            $maxCol      = $sheet->getHighestDataColumn();
            $maxRow      = min((int) $sheet->getHighestDataRow(), 51);
            $maxColN     = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCol);

            $rows = [];
            for ($r = 1; $r <= $maxRow; $r++) {
                $row = [];
                for ($c = 1; $c <= $maxColN; $c++) {
                    $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                    $cell  = $sheet->getCell($letra . $r);
                    $raw   = $cell->getValue();

                    // Datas: valor numérico com formato de data → converte para d/m/Y
                    if (is_float($raw) || is_int($raw)) {
                        $fmt = $cell->getStyle()->getNumberFormat()->getFormatCode();
                        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTimeFormatCode($fmt)) {
                            $ts   = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($raw);
                            $row[] = date("d/m/Y", $ts);
                            continue;
                        }
                    }

                    // Texto: usa o valor calculado e decodifica HTML entities
                    $val   = $cell->getCalculatedValue();
                    $row[] = html_entity_decode((string) $val, ENT_QUOTES | ENT_HTML5, "UTF-8");
                }
                $rows[] = $row;
            }

            header("Content-Type: application/json; charset=utf-8");
            echo json_encode(["error" => false, "rows" => $rows, "maxCol" => $maxColN], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (\Throwable $e) {
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode(["error" => true, "message" => $e->getMessage()]);
        }
    }

    public function salvarMapeamento(Request $request): void
    {
        $this->authorize("projeto_gerenciar");

        $data    = new Data($request->all());
        $projeto = Projeto::find((int) ($data->id ?? 0));

        if (!$projeto) {
            $this->message->warning("Projeto não encontrado");
            $this->router->redirect("admin.projeto.index");
            return;
        }

        $upload = $this->session->get("planilha_upload");

        if (!$upload || ($upload->projeto ?? null) != $projeto->id) {
            $this->message->warning("Sessão expirada. Faça o upload novamente");
            $this->router->redirect("admin.projeto.importacao", ["id" => $projeto->id]);
            return;
        }

        $tipo    = $upload->tipo;
        $aba     = (int) ($data->aba ?? 0);
        $mapa    = (array) ($data->mapeamento ?? []);
        $headers = (array) ($data->headers ?? []);

        // Remove mapeamentos anteriores desta combinação projeto+tipo+aba
        DB::table("projeto_mapeamento_coluna")
            ->where("id_projeto", "=", $projeto->id)
            ->where("tipo_demonstrativo", "=", $tipo)
            ->where("aba", "=", $aba)
            ->delete();

        foreach ($mapa as $indice => $valor) {
            $valor = trim((string) $valor);
            if ($valor === "") continue;

            ProjetoMapeamentoColuna::create([
                "id_projeto"        => $projeto->id,
                "tipo_demonstrativo" => $tipo,
                "aba"               => $aba,
                "indice_coluna"     => (int) $indice,
                "header_original"   => trim((string) ($headers[$indice] ?? "Col " . ($indice + 1))),
                "mapeamento"        => $valor,
            ]);
        }

        $this->message->success("Mapeamento salvo com sucesso");
        $this->router->redirect("admin.projeto.importacao.mapear", ["id" => $projeto->id]);
    }

    public function processarDados(Request $request): void
    {
        $this->authorize("projeto_gerenciar");

        $data    = new Data($request->all());
        $projeto = Projeto::find((int) ($data->id ?? 0));

        if (!$projeto) {
            $this->message->warning("Projeto não encontrado");
            $this->router->redirect("admin.projeto.index");
            return;
        }

        $upload = $this->session->get("planilha_upload");

        if (!$upload || ($upload->projeto ?? null) != $projeto->id) {
            $this->message->warning("Sessão expirada. Faça o upload novamente");
            $this->router->redirect("admin.projeto.importacao", ["id" => $projeto->id]);
            return;
        }

        $tipo = $upload->tipo;
        $aba  = (int) ($data->aba ?? 0);

        $caminho = PATH_ROOT . "/storage/tmp/planilhas/" . $upload->arquivo;
        if (!file_exists($caminho)) {
            $this->message->warning("Arquivo não encontrado. Faça o upload novamente");
            $this->session->unset("planilha_upload");
            $this->router->redirect("admin.projeto.importacao", ["id" => $projeto->id]);
            return;
        }

        // Carrega o mapeamento salvo
        $mapeamento = ProjetoMapeamentoColuna::porProjeto($projeto->id, $tipo, $aba);
        if (empty($mapeamento)) {
            $this->message->warning("Nenhum mapeamento salvo para esta aba. Salve o mapeamento primeiro.");
            $this->router->redirect("admin.projeto.importacao.mapear", ["id" => $projeto->id]);
            return;
        }

        // Prepara índices: [indice_coluna => mapeamento]
        $mapa = [];
        foreach ($mapeamento as $m) {
            $mapa[(int) $m->indice_coluna] = $m->mapeamento;
        }

        try {
            $reader      = IOFactory::createReaderForFile($caminho);
            $spreadsheet = $reader->load($caminho);
            $sheet       = $spreadsheet->getSheet($aba);
            $highestRow  = (int) $sheet->getHighestDataRow();
            $highestCol  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

            // Remove lançamentos anteriores deste projeto+tipo+aba
            ProjetoLancamento::limparPorProjeto($projeto->id, $tipo, $aba);

            $inseridos = 0;

            for ($r = 2; $r <= $highestRow; $r++) {
                $valoresCelula = [];
                $periodo  = null;
                $descricao = null;
                $unidade  = null;

                // Primeiro passa: lê todos os valores da linha
                for ($c = 1; $c <= $highestCol; $c++) {
                    $letra   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                    $cell    = $sheet->getCell($letra . $r);
                    $raw     = $cell->getValue();
                    $cellVal = null;

                    if (is_float($raw) || is_int($raw)) {
                        $fmt = $cell->getStyle()->getNumberFormat()->getFormatCode();
                        if (ExcelDate::isDateTimeFormatCode($fmt)) {
                            $ts       = ExcelDate::excelToTimestamp($raw);
                            $cellVal  = date("Y-m-d", $ts);
                        } else {
                            $cellVal = (string) $raw;
                        }
                    } else {
                        $cellVal = trim(html_entity_decode(
                            (string) $cell->getCalculatedValue(),
                            ENT_QUOTES | ENT_HTML5, "UTF-8"
                        ));
                    }

                    $valoresCelula[$c] = $cellVal;
                }

                // Pula linha totalmente vazia
                $valoresPreenchidos = array_filter($valoresCelula, fn ($v) => $v !== null && $v !== "");
                if (empty($valoresPreenchidos)) continue;

                // Extrai campos especiais
                foreach ($mapa as $colIdx => $mapping) {
                    $colIdx++; // 1-based para acessar $valoresCelula
                    $rawVal = $valoresCelula[$colIdx] ?? "";

                    if ($mapping === "__periodo__") {
                        $periodo = self::parsePeriodo($rawVal);
                    } elseif ($mapping === "__descricao__") {
                        $descricao = $rawVal ?: null;
                    } elseif ($mapping === "__unidade__") {
                        $unidade = $rawVal ?: null;
                    }
                }

                // Segundo passa: cria lançamentos para colunas mapeadas como conta_N
                foreach ($mapa as $colIdx => $mapping) {
                    if (!str_starts_with($mapping, "conta_")) continue;

                    $idConta = (int) substr($mapping, 6);
                    if ($idConta <= 0) continue;

                    $colIdx++; // 1-based
                    $rawVal  = $valoresCelula[$colIdx] ?? "";
                    $rawVal  = str_replace([".", ","], ["", "."], (string) $rawVal);
                    $valor   = is_numeric($rawVal) ? (float) $rawVal : null;

                    if ($valor === null || $valor === 0.0) continue;

                    ProjetoLancamento::create([
                        "id_projeto"         => $projeto->id,
                        "tipo_demonstrativo"  => $tipo,
                        "aba"                => $aba,
                        "id_dre_conta"       => $idConta,
                        "periodo"            => $periodo,
                        "descricao"          => $descricao,
                        "valor"              => $valor,
                        "unidade"            => $unidade,
                        "linha"              => $r,
                        "mapeamento"         => $mapping,
                        "created_by"         => $this->user->uid,
                    ]);

                    $inseridos++;
                }
            }

            $this->message->success("{$inseridos} lançamento(s) importado(s) com sucesso");
            $this->router->redirect("admin.projeto.importacao.mapear", ["id" => $projeto->id]);

        } catch (\Throwable $e) {
            $this->message->error("Erro ao processar dados: " . $e->getMessage());
            $this->router->redirect("admin.projeto.importacao.mapear", ["id" => $projeto->id]);
        }
    }

    private static function parsePeriodo(?string $raw): ?string
    {
        if (!$raw) return null;

        $raw = trim($raw);

        // Já veio como data ISO (Y-m-d)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        // "mm/yyyy" ou "mm/yy"
        if (preg_match('/^(\d{1,2})[\/](\d{2,4})$/', $raw, $m)) {
            $mes = str_pad($m[1], 2, "0", STR_PAD_LEFT);
            $ano = strlen($m[2]) === 2 ? "20" . $m[2] : $m[2];
            return "{$ano}-{$mes}-01";
        }

        // "mm/yyyy" via barra (dd/mm/yyyy) — pega só mês/ano
        if (preg_match('/^(\d{2})[\/](\d{2})[\/](\d{4})$/', $raw, $m)) {
            return "{$m[3]}-{$m[2]}-01";
        }

        // "yyyy/mm" 
        if (preg_match('/^(\d{4})[\/](\d{1,2})$/', $raw, $m)) {
            $mes = str_pad($m[2], 2, "0", STR_PAD_LEFT);
            return "{$m[1]}-{$mes}-01";
        }

        // Data Excel serial (numérico)
        if (is_numeric($raw)) {
            try {
                $ts = ExcelDate::excelToTimestamp((int) $raw);
                return date("Y-m-d", $ts);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    public function sugerirMapeamento(Request $request): void
    {
        $this->authorize("projeto_gerenciar");

        header("Content-Type: application/json; charset=utf-8");

        $data   = new Data($request->all());
        $upload = $this->session->get("planilha_upload");

        if (!$upload || ($upload->projeto ?? null) != (int) ($data->id ?? 0)) {
            echo json_encode(["ok" => false, "error" => "Sessão expirada. Recarregue a página."]);
            return;
        }

        $headers = (array) ($data->headers ?? []);
        if (empty($headers)) {
            echo json_encode(["ok" => false, "error" => "Nenhum cabeçalho recebido."]);
            return;
        }

        // Monta lista de opções disponíveis para o prompt
        $opcoes   = [];
        $opcoes[] = "__periodo__   → Período / Competência";
        $opcoes[] = "__descricao__ → Descrição / Histórico";
        $opcoes[] = "__valor__     → Valor";
        $opcoes[] = "__unidade__   → Unidade / Centro de Custo";

        $gruposContas = DreConta::analiticasPorTipo($upload->tipo);
        $contas       = $gruposContas ? array_merge(...array_values($gruposContas)) : [];
        foreach ($contas as $conta) {
            $opcoes[] = "conta_{$conta->id} → {$conta->codigo} — {$conta->nome}";
        }

        // Monta lista de colunas a analisar
        $linhasColunas = [];
        foreach ($headers as $i => $h) {
            $linhasColunas[] = "  [{$i}] " . trim((string) $h);
        }

        $systemPrompt = <<<PROMPT
Você é um assistente especialista em contabilidade e finanças empresariais, com foco em demonstrativos financeiros (DRE, DFC, Balanço Patrimonial).
Sua tarefa é analisar nomes de colunas de uma planilha importada de terceiros e sugerir o mapeamento mais adequado para cada coluna, com base nas opções disponíveis.
Responda SOMENTE com um objeto JSON válido, sem markdown, sem explicações, sem texto adicional.
O formato da resposta deve ser exatamente:
{"sugestoes": {"0": "valor_opcao", "1": "valor_opcao", ...}}
Onde a chave é o índice da coluna (string) e o valor é um dos identificadores das opções disponíveis.
Se uma coluna não se encaixar em nenhuma opção, use string vazia "".
PROMPT;

        $prompt = "Tipo de demonstrativo: " . strtoupper($upload->tipo) . "\n\n";
        $prompt .= "Colunas da planilha:\n" . implode("\n", $linhasColunas) . "\n\n";
        $prompt .= "Opções disponíveis para mapeamento:\n" . implode("\n", $opcoes);

        try {
            $ai     = new ChatGPT();
            $result = $ai->send($systemPrompt, $prompt, "", null, ["retries" => 3]);

            if (!$result["ok"]) {
                echo json_encode(["ok" => false, "error" => $result["error"] ?? "Erro na IA."]);
                return;
            }

            // Extrai JSON da resposta (remove eventual markdown se o modelo retornar)
            $text = trim($result["text"] ?? "");
            $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
            $text = preg_replace('/\s*```$/', '', $text);

            $decoded = json_decode($text, true);

            if (!isset($decoded["sugestoes"]) || !is_array($decoded["sugestoes"])) {
                echo json_encode(["ok" => false, "error" => "Resposta da IA em formato inesperado."]);
                return;
            }

            // Valida que os valores retornados existem nas opções permitidas
            $valoresPermitidos = ["", "__periodo__", "__descricao__", "__valor__", "__unidade__"];
            foreach ($contas as $conta) {
                $valoresPermitidos[] = "conta_{$conta->id}";
            }

            $sugestoes = [];
            foreach ($decoded["sugestoes"] as $idx => $val) {
                $val = trim((string) $val);
                $sugestoes[(string)(int) $idx] = in_array($val, $valoresPermitidos) ? $val : "";
            }

            echo json_encode(["ok" => true, "sugestoes" => $sugestoes], JSON_UNESCAPED_UNICODE);

        } catch (\Throwable $e) {
            echo json_encode(["ok" => false, "error" => $e->getMessage()]);
        }
    }

    private function rememberVisitedProject(object $projeto): void
    {
        $idUsuario = (int) ($this->user->uid ?? 0);
        $idProjeto = (int) ($projeto->id ?? 0);

        UsuarioProjetoRecente::registrarVisualizacao($idUsuario, $idProjeto);

        if ($idUsuario > 0) {
            $guard = $this->auth->getGuard();
            $this->cache->clear("menu_admin_{$guard}_{$idUsuario}");
            $this->cache->clear(MenuService::recentProjectsCacheKey($idUsuario));
        }
    }
}
