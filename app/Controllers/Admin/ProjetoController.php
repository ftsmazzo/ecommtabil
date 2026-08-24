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
use App\Services\Importacao\PlanilhaImportacaoService;
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

        $origem = in_array((string) ($data->origem ?? ""), ["template", "livre"], true)
            ? (string) $data->origem
            : "livre";

        $this->session->set("planilha_upload", [
            "arquivo"       => $nome,
            "original"      => $file["name"],
            "tipo"          => $tipoDemo,
            "projeto"       => $projeto->id,
            "origem"        => $origem,
            "conta_padrao"  => null,
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
            $svc        = new PlanilhaImportacaoService();
            $aberto     = $svc->abrir($caminho);
            $sheetNames = $aberto["sheetNames"];

            $abaAtiva = (int) ($data->aba ?? 0);
            if ($abaAtiva < 0 || $abaAtiva >= count($sheetNames)) {
                $abaAtiva = 0;
            }

            $sheet   = $aberto["spreadsheet"]->getSheet($abaAtiva);
            $lido    = $svc->lerCabecalhos($sheet, 3);
            $headers = $lido["headers"];
            $previews = $lido["previews"];
            $colunas = $svc->rotulosColunas($headers, $previews);
            $linhaCabecalho = (int) ($lido["linhaCabecalho"] ?? 1);

            DreConta::garantirPlanoSagaPadrao((string) $upload->tipo, (int) ($this->user->uid ?? 0));
            $contasGrupos = DreConta::analiticasPorTipo($upload->tipo);
            $contasLista  = DreConta::analiticasLista($upload->tipo);
            $dePara       = $svc->camposDePara((string) $upload->tipo, $contasGrupos);
            $contaPadrao  = $this->contaPadraoDoUpload($upload);

            $mapaSalvo = ProjetoMapeamentoColuna::porProjeto((int) $projeto->id, (string) $upload->tipo, $abaAtiva);
            $layoutDetectado = $svc->detectarLayout($headers);

            if ($mapaSalvo) {
                $layout           = $svc->layoutDoMapa($mapaSalvo, $contaPadrao);
                $expandido        = $svc->expandirMapa($mapaSalvo);
                $origemMapeamento = "salvo";
            } else {
                $expandido        = ["campos" => [], "periodos_matriz" => [], "ano_base" => (int) date("Y")];
                $origemMapeamento = "vazio";
                $colunasModelo    = $svc->colunasModeloPorTipo((string) $upload->tipo);
                if (($upload->origem ?? "") === "template" && $colunasModelo) {
                    $peloModelo = $svc->sugerirPeloModelo($headers, $colunasModelo);
                    if (!empty($peloModelo["campos"])) {
                        $expandido        = $peloModelo + ["ano_base" => (int) date("Y")];
                        $origemMapeamento = "sugerido";
                    }
                }
                if (empty($expandido["campos"])) {
                    $expandido        = $svc->sugerirCampos($headers, $layoutDetectado, $contasLista);
                    $origemMapeamento = ($expandido["campos"] || $expandido["periodos_matriz"]) ? "sugerido" : "vazio";
                }
                $layout = $layoutDetectado;
            }

            $campos         = $expandido["campos"];
            $periodosMatriz = $expandido["periodos_matriz"];
            if ($origemMapeamento === "sugerido" && !$contaPadrao) {
                $temValor  = isset($campos[PlanilhaImportacaoService::DEST_VALOR]);
                $temConta  = isset($campos[PlanilhaImportacaoService::DEST_CONTA]);
                $temContaN = false;
                foreach (array_keys($campos) as $dest) {
                    if (str_starts_with((string) $dest, "conta_")) {
                        $temContaN = true;
                        break;
                    }
                }
                if ($temValor && !$temConta && !$temContaN) {
                    foreach ($contasLista as $c) {
                        if (strcasecmp(trim((string) $c->nome), "Receita Bruta") === 0) {
                            $contaPadrao = (int) $c->id;
                            break;
                        }
                    }
                }
            }
            $anoBase        = (int) ($expandido["ano_base"] ?? date("Y"));
            if ($anoBase < 1990 || $anoBase > 2100) {
                $anoBase = (int) date("Y");
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
                "Projetos"              => ["url" => $this->router->route("admin.projeto.index"), "current" => false],
                $projetoLabel           => ["url" => $this->router->route("admin.projeto.abrir", ["id" => $projeto->id]), "current" => false],
                "Importação"            => ["url" => $this->router->route("admin.projeto.importacao", ["id" => $projeto->id]), "current" => false],
                "De-para"               => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "De-para de colunas",
                "desc"  => $projetoLabel . " — " . strtoupper($upload->tipo) . ": o que o demonstrativo precisa × coluna da planilha",
            ],
        ]);

        $lancamentosExistentes = ProjetoLancamento::resumoPorProjeto($projeto->id, $upload->tipo);

        echo $this->view->render("admin/projeto/mapeamento", [
            "projeto"               => $projeto,
            "upload"                => $upload,
            "sheetNames"            => $sheetNames,
            "abaAtiva"              => $abaAtiva,
            "headers"               => $headers,
            "previews"              => $previews,
            "colunas"               => $colunas,
            "contas"                => $contasGrupos,
            "camposModelo"          => $dePara["campos"],
            "modeloParametrizado"   => $dePara["parametrizado"],
            "contaPadrao"           => $contaPadrao,
            "tipos"                 => TipoDemonstrativo::options(),
            "layout"                => $layout,
            "layoutDetectado"       => $layoutDetectado,
            "campos"                => $campos,
            "periodosMatriz"        => $periodosMatriz,
            "anoBase"               => $anoBase,
            "origemMapeamento"      => $origemMapeamento,
            "mapeamentoSalvo"       => $mapaSalvo,
            "lancamentosExistentes" => $lancamentosExistentes,
            "linhaCabecalho"        => $linhaCabecalho,
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
            $svc    = new PlanilhaImportacaoService();
            $aberto = $svc->abrir($caminho);
            $sheet  = $aberto["spreadsheet"]->getSheet($abaIdx);
            $lido   = $svc->lerCabecalhos($sheet, 0);
            $maxCol = $lido["highestCol"];
            $maxRow = min($lido["highestRow"], 51);

            $rows = [];
            for ($r = 1; $r <= $maxRow; $r++) {
                $row = [];
                for ($c = 1; $c <= $maxCol; $c++) {
                    $row[] = $svc->celula($sheet, $r, $c, false);
                }
                $rows[] = $row;
            }

            header("Content-Type: application/json; charset=utf-8");
            echo json_encode(["error" => false, "rows" => $rows, "maxCol" => $maxCol], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

        $caminho = PATH_ROOT . "/storage/tmp/planilhas/" . $upload->arquivo;
        if (!file_exists($caminho)) {
            $this->message->warning("Arquivo não encontrado. Faça o upload novamente");
            $this->session->unset("planilha_upload");
            $this->router->redirect("admin.projeto.importacao", ["id" => $projeto->id]);
            return;
        }

        $tipo = $upload->tipo;
        $aba  = (int) ($data->aba ?? 0);
        $svc  = new PlanilhaImportacaoService();

        try {
            $aberto  = $svc->abrir($caminho);
            $sheet   = $aberto["spreadsheet"]->getSheet($aba);
            $headers = $svc->lerCabecalhos($sheet, 0)["headers"];
        } catch (\Throwable $e) {
            $this->message->error("Não foi possível ler o arquivo: " . $e->getMessage());
            $this->router->redirect("admin.projeto.importacao.mapear", ["id" => $projeto->id]);
            return;
        }

        $anoBase = (int) ($data->ano_base ?? date("Y"));
        if ($anoBase < 1990 || $anoBase > 2100) {
            $anoBase = (int) date("Y");
        }

        $contaPadrao = (int) ($data->conta_padrao ?? 0);
        if ($contaPadrao <= 0) {
            $contaPadrao = null;
        }

        $linhas = $svc->compactarCampos(
            (array) ($data->campos ?? []),
            $headers,
            (array) ($data->periodos_matriz ?? []),
            $anoBase
        );

        $mapa = [];
        foreach ($linhas as $linha) {
            $mapa[(int) $linha["indice_coluna"]] = $linha["mapeamento"];
        }

        $layout   = $svc->layoutDoMapa($mapa, $contaPadrao);
        $validado = $svc->validar($mapa, $layout, $contaPadrao);
        if (!$validado["ok"]) {
            $this->message->warning($validado["erro"] ?? "Mapeamento incompleto.");
            $this->router->redirect("admin.projeto.importacao.mapear", ["id" => $projeto->id]);
            return;
        }

        DB::table("projeto_mapeamento_coluna")
            ->where("id_projeto", "=", $projeto->id)
            ->where("tipo_demonstrativo", "=", $tipo)
            ->where("aba", "=", $aba)
            ->delete();

        foreach ($linhas as $linha) {
            ProjetoMapeamentoColuna::create([
                "id_projeto"         => $projeto->id,
                "tipo_demonstrativo" => $tipo,
                "aba"                => $aba,
                "indice_coluna"      => $linha["indice_coluna"],
                "header_original"    => $linha["header_original"],
                "mapeamento"         => $linha["mapeamento"],
            ]);
        }

        $this->atualizarUploadSessao($upload, ["conta_padrao" => $contaPadrao]);

        $this->message->success("Mapeamento salvo com sucesso");
        $this->router->redirect("admin.projeto.importacao.mapear", ["id" => $projeto->id, "aba" => $aba]);
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

        $tipo    = $upload->tipo;
        $aba     = (int) ($data->aba ?? 0);
        $caminho = PATH_ROOT . "/storage/tmp/planilhas/" . $upload->arquivo;

        if (!file_exists($caminho)) {
            $this->message->warning("Arquivo não encontrado. Faça o upload novamente");
            $this->session->unset("planilha_upload");
            $this->router->redirect("admin.projeto.importacao", ["id" => $projeto->id]);
            return;
        }

        $svc         = new PlanilhaImportacaoService();
        $mapa        = ProjetoMapeamentoColuna::porProjeto((int) $projeto->id, (string) $tipo, $aba);
        $contaPadrao = $this->contaPadraoDoUpload($upload);
        if (empty($mapa)) {
            $this->message->warning("Nenhum mapeamento salvo para esta aba. Salve o mapeamento primeiro.");
            $this->router->redirect("admin.projeto.importacao.mapear", ["id" => $projeto->id, "aba" => $aba]);
            return;
        }

        $validado = $svc->validar($mapa, $svc->layoutDoMapa($mapa, $contaPadrao), $contaPadrao);
        if (!$validado["ok"]) {
            $this->message->warning($validado["erro"] ?? "Mapeamento incompleto.");
            $this->router->redirect("admin.projeto.importacao.mapear", ["id" => $projeto->id, "aba" => $aba]);
            return;
        }

        try {
            $resultado = $svc->processar(
                $caminho,
                $aba,
                $mapa,
                (int) $projeto->id,
                (string) $tipo,
                (int) $this->user->uid,
                true,
                $contaPadrao
            );

            if ($resultado["inseridos"] === 0) {
                $this->message->warning("Nenhum lançamento foi gerado. Revise o mapeamento, os nomes das contas e o formato dos valores.");
            } else {
                $msg = $resultado["inseridos"] . " lançamento(s) a partir de "
                    . (int) ($resultado["linhas_origem"] ?? $resultado["inseridos"]) . " linha(s) da planilha";
                if ($resultado["ignorados"] > 0) {
                    $msg .= " · " . $resultado["ignorados"] . " linha(s) ignorada(s)";
                }
                $this->message->success($msg);
            }

            foreach ($resultado["avisos"] as $aviso) {
                $this->message->warning($aviso);
            }

            $this->router->redirect("admin.projeto.importacao.mapear", ["id" => $projeto->id, "aba" => $aba]);
        } catch (\Throwable $e) {
            $this->message->error("Erro ao processar dados: " . $e->getMessage());
            $this->router->redirect("admin.projeto.importacao.mapear", ["id" => $projeto->id, "aba" => $aba]);
        }
    }

    public function simularDados(Request $request): void
    {
        $this->authorize("projeto_gerenciar");
        header("Content-Type: application/json; charset=utf-8");

        $data    = new Data($request->all());
        $upload  = $this->session->get("planilha_upload");
        $projeto = Projeto::find((int) ($data->id ?? 0));

        if (!$projeto || !$upload || ($upload->projeto ?? null) != $projeto->id) {
            echo json_encode(["ok" => false, "error" => "Sessão expirada. Faça o upload novamente."]);
            return;
        }

        $tipo    = $upload->tipo;
        $aba     = (int) ($data->aba ?? 0);
        $caminho = PATH_ROOT . "/storage/tmp/planilhas/" . $upload->arquivo;
        if (!file_exists($caminho)) {
            echo json_encode(["ok" => false, "error" => "Arquivo não encontrado."]);
            return;
        }

        $svc         = new PlanilhaImportacaoService();
        $mapa        = ProjetoMapeamentoColuna::porProjeto((int) $projeto->id, (string) $tipo, $aba);
        $contaPadrao = $this->contaPadraoDoUpload($upload);
        if (empty($mapa)) {
            echo json_encode(["ok" => false, "error" => "Salve o mapeamento antes de conferir."]);
            return;
        }

        $validado = $svc->validar($mapa, $svc->layoutDoMapa($mapa, $contaPadrao), $contaPadrao);
        if (!$validado["ok"]) {
            echo json_encode(["ok" => false, "error" => $validado["erro"] ?? "Mapeamento incompleto."]);
            return;
        }

        try {
            $resultado = $svc->processar(
                $caminho,
                $aba,
                $mapa,
                (int) $projeto->id,
                (string) $tipo,
                (int) $this->user->uid,
                false,
                $contaPadrao
            );
            echo json_encode(["ok" => true] + $resultado, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(["ok" => false, "error" => $e->getMessage()]);
        }
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
        if ($headers === []) {
            echo json_encode(["ok" => false, "error" => "Nenhum cabeçalho recebido."]);
            return;
        }

        $svc    = new PlanilhaImportacaoService();
        $layout = (string) ($data->layout ?? "");
        if (!in_array($layout, [
            PlanilhaImportacaoService::LAYOUT_COLUNAR,
            PlanilhaImportacaoService::LAYOUT_LEDGER,
            PlanilhaImportacaoService::LAYOUT_MATRIZ,
        ], true)) {
            $layout = $svc->detectarLayout($headers);
        }

        $contas = DreConta::analiticasLista($upload->tipo);

        $linhasColunas = [];
        foreach ($headers as $i => $h) {
            $letra = $svc->letra((int) $i);
            $linhasColunas[] = "  [{$i}] {$letra} — " . trim((string) $h);
        }

        $dePara = $svc->camposDePara((string) $upload->tipo, DreConta::analiticasPorTipo($upload->tipo));
        $camposSistema = [];
        foreach ($dePara["campos"] as $campo) {
            $camposSistema[] = $campo["destino"] . " → " . $campo["label"];
        }
        $camposSistema[] = "__periodo__ → Período / Data";
        $camposSistema[] = "__descricao__ → Descrição";
        $camposSistema[] = "__valor__ → Valor";
        $camposSistema[] = "__unidade__ → Unidade / Centro de custo";
        $camposSistema[] = "__conta__ → Nome ou código da conta (texto da célula)";
        foreach ($contas as $conta) {
            $camposSistema[] = "conta_{$conta->id} → {$conta->codigo} — {$conta->nome}";
        }
        $camposSistema[] = "periodos_matriz → índices das colunas que são anos/meses (matriz)";

        $camposSistema = array_values(array_unique($camposSistema));

        $systemPrompt = <<<PROMPT
Você é um assistente especialista em contabilidade e finanças empresariais (DRE, DFC, Balanço).
Sua tarefa é mapear CAMPOS DO SISTEMA para COLUNAS da planilha (não o contrário).
Responda SOMENTE com um objeto JSON válido, sem markdown e sem texto extra.
Formato exatamente:
{"campos": {"__periodo__": 0, "conta_12": 3}, "periodos_matriz": [1, 2]}
- A chave de "campos" é o identificador do campo do sistema.
- O valor é o índice inteiro (0-based) da coluna da planilha.
- "periodos_matriz" só se aplica ao layout matriz: lista de índices das colunas de período/valor.
- Não atribua a mesma coluna a dois campos.
- Se um campo não tiver coluna óbvia, omita-o.
PROMPT;

        $prompt  = "Tipo de demonstrativo: " . strtoupper((string) $upload->tipo) . "\n";
        $prompt .= "A planilha pode ser de marketplace (Shopee, Mercado Livre), ERP ou o template do sistema.\n";
        $prompt .= "Priorize: Data/Período, Descrição/Produto, Valor/Total. Só use conta_N se o cabeçalho for claramente essa conta.\n\n";
        $prompt .= "Colunas da planilha:\n" . implode("\n", $linhasColunas) . "\n\n";
        $prompt .= "Campos do sistema:\n" . implode("\n", $camposSistema);

        $idsValidos = [];
        foreach ($contas as $conta) {
            $idsValidos["conta_" . $conta->id] = true;
        }
        $especiais = [
            PlanilhaImportacaoService::DEST_PERIODO   => true,
            PlanilhaImportacaoService::DEST_DESCRICAO => true,
            PlanilhaImportacaoService::DEST_VALOR     => true,
            PlanilhaImportacaoService::DEST_UNIDADE   => true,
            PlanilhaImportacaoService::DEST_CONTA     => true,
        ];
        $maxIndice = count($headers) - 1;

        try {
            $ai     = new ChatGPT();
            $result = $ai->send($systemPrompt, $prompt, "", null, ["retries" => 3]);

            if (!$result["ok"]) {
                echo json_encode(["ok" => false, "error" => $result["error"] ?? "Erro na IA."]);
                return;
            }

            $text = trim((string) ($result["text"] ?? ""));
            $text = preg_replace('/^```(?:json)?\s*/i', "", $text);
            $text = preg_replace('/\s*```$/', "", $text);

            $decoded = json_decode($text, true);
            if (!is_array($decoded)) {
                echo json_encode(["ok" => false, "error" => "Resposta da IA em formato inesperado."]);
                return;
            }

            $camposBrutos = is_array($decoded["campos"] ?? null) ? $decoded["campos"] : [];
            $matrizBrutos = is_array($decoded["periodos_matriz"] ?? null) ? $decoded["periodos_matriz"] : [];

            $campos = [];
            $usados = [];
            foreach ($camposBrutos as $destino => $indice) {
                $destino = trim((string) $destino);
                $indice  = (int) $indice;
                if ($indice < 0 || $indice > $maxIndice || isset($usados[$indice])) {
                    continue;
                }
                $ok = isset($especiais[$destino]) || isset($idsValidos[$destino]);
                if (!$ok) {
                    continue;
                }
                $campos[$destino] = $indice;
                $usados[$indice]  = true;
            }

            $periodos = [];
            foreach ($matrizBrutos as $indice) {
                $indice = (int) $indice;
                if ($indice < 0 || $indice > $maxIndice || isset($usados[$indice])) {
                    continue;
                }
                $periodos[] = $indice;
                $usados[$indice] = true;
            }

            echo json_encode([
                "ok"              => true,
                "layout"          => $layout,
                "campos"          => $campos,
                "periodos_matriz" => $periodos,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(["ok" => false, "error" => $e->getMessage()]);
        }
    }

    private function contaPadraoDoUpload(object $upload): ?int
    {
        $id = (int) ($upload->conta_padrao ?? 0);
        return $id > 0 ? $id : null;
    }

    private function atualizarUploadSessao(object $upload, array $extra): void
    {
        $this->session->set("planilha_upload", array_merge([
            "arquivo"      => $upload->arquivo ?? "",
            "original"     => $upload->original ?? "",
            "tipo"         => $upload->tipo ?? "",
            "projeto"      => $upload->projeto ?? 0,
            "origem"       => $upload->origem ?? "livre",
            "conta_padrao" => $upload->conta_padrao ?? null,
        ], $extra));
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
