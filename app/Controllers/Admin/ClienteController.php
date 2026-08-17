<?php
namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\Config;
use App\Core\Redirect;
use App\Core\Request;
use App\Enums\EstadoBrasileiro;
use App\Enums\CartaoStatusEnum;
use App\Models\Cliente;
use App\Models\Cartao;
use App\Models\Empresa;
use App\Lib\Cnpj;
use App\Models\ClienteRamo;
use App\Models\ClienteSituacao;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Controller de clientes.
 *
 * Centraliza o fluxo do cadastro de clientes no admin:
 * - monta os dados base da tela;
 * - lista clientes com dados derivados para a view;
 * - abre e processa os formularios de novo/editar;
 * - consulta CNPJ na fonte externa;
 * - importa clientes por planilha em lotes.
 */
class ClienteController extends ControllerAdmin
{
    /**
     * Injeta no layout tudo o que a tela de clientes precisa de forma global.
     *
     * Aqui ficam os parametros compartilhados entre listagem, novo e edicao:
     * - titulo e menu ativo;
     * - permissao de ramo;
     * - configuracao de PF/PJ;
     * - campos em upper e required;
     * - lista de UFs para validacao no front.
     */
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title" => "Grupos de Clientes",
            "active_menu" => "clientes-clientes",
            "page" => [
                "title" => "Grupos de Clientes",
                "desc" => "Cadastre e gerencie seus grupos de clientes",
            ],
            "csrf" => $this->csrf->generate(),
            "usa_ramo" => $this->useRamoCliente(),
            "pessoa_opcoes" => $this->pessoaOpcoesCliente(),
            "pessoa_fixa" => $this->pessoaFixaCliente(),
            "uppers" => implode(",", Cliente::getUppers()),
            "required" => implode(",", Cliente::getRequired()),
            "estados_br" => array_map(
                fn (EstadoBrasileiro $estado) => $estado->value,
                EstadoBrasileiro::cases()
            ),
        ]);
    }

    /**
     * Lista os clientes cadastrados com campos derivados para exibicao.
     *
     * A query faz left join em situacao e ramo para nao quebrar quando um
     * cliente nao tiver relacionamento definido. Depois, o controller monta
     * textos e badges prontos para a view.
     */
    public function index(): void
    {
        $this->authorize("cliente_gerenciar");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Grupos de Clientes" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Grupos de Clientes",
                "desc" => "Cadastre e gerencie seus grupos de clientes",
            ],
            "usa_ramo" => $this->useRamoCliente(),
        ]);

        $clientes = Cliente::select("c.*")
            ->orderBy("c.razao")
            ->get();

        $resumoEmpresas = Empresa::select(
            "e.id_cliente",
            "COUNT(e.id) AS total_empresas"
        )
            ->groupBy("e.id_cliente")
            ->get();

        $resumoEmpresasMap = [];
        foreach ($resumoEmpresas as $resumo) {
            $resumoEmpresasMap[(int) ($resumo->id_cliente ?? 0)] = (int) $resumo->total_empresas;
        }

        $permissao = [
            "inserir" => $this->auth->allow("cliente_inserir"),
            "editar" => $this->auth->allow("cliente_editar"),
            "excluir" => $this->auth->allow("cliente_excluir"),
            "situacao" => $this->auth->allow("cliente_situacao_gerenciar"),
            "ramo" => $this->auth->allow("cliente_ramo_gerenciar"),
        ];

        foreach ($clientes as $cliente) {
            $cliente->principal_print = trim((string) ($cliente->razao ?: $cliente->nome)) ?: "-";
            $cliente->empresas_total = $resumoEmpresasMap[(int) $cliente->id] ?? 0;
            $cliente->empresas_action = $this->router->route("admin.cliente.editar", ["id" => $cliente->id]);
            $cliente->disabled = $permissao["excluir"] ? "" : "disabled";
            $cliente->action = $permissao["excluir"] ? 'onclick="Delete(\'clientes/delete\', \'' . $cliente->id . '\')"' : "";
            $cliente->title = $permissao["excluir"] ? "Excluir grupo" : "Sem permissão";
        }

        echo $this->view->render("admin/cliente/lista", [
            "dados" => $clientes,
            "permissao" => $permissao,
            "usa_ramo" => $this->useRamoCliente(),
            "modelo_importacao_url" => $this->importTemplateUrl(),
        ]);
    }

    /**
     * Lista os cartões vinculados a um cliente e exibe um resumo rapido.
     */
    public function cartoes(Request $request): void
    {
        $this->authorize("cliente_gerenciar");

        $data = new Data($request->all());
        $cliente = Cliente::leftJoin("cliente_situacao as s", "c.id_situacao", "=", "s.id")
            ->leftJoin("cliente_ramo as r", "c.id_ramo", "=", "r.id")
            ->select("c.*", "s.descricao as situacao_descricao", "s.cor as situacao_cor", "r.descricao as ramo_descricao")
            ->where("c.id", "=", (int) ($data->id ?? 0))
            ->first();

        if (!$cliente) {
            $this->message->warning("Cliente não encontrado.");
            $this->router->redirect("admin.cliente.index");
            return;
        }

        $cartoes = Cartao::leftJoin("cliente as cl", "ct.id_cliente", "=", "cl.id")
            ->select(
                "ct.*",
                "cl.razao as cliente_razao",
                "cl.nome as cliente_nome",
                "cl.pessoa as cliente_pessoa",
                "cl.documento as cliente_documento",
                "cl.telefone as cliente_telefone"
            )
            ->where("ct.id_cliente", "=", (int) $cliente->id)
            ->orderBy("ct.created_at", "desc")
            ->get();

        $totalCartoes = count($cartoes);
        $saldoTotal = 0.0;

        foreach ($cartoes as $cartao) {
            $saldoTotal += (float) ($cartao->saldo ?? 0);
            $cartao->codigo_print = $this->shortValue($cartao->codigo_unico ?? "", 18);
            $cartao->saldo_print = $this->moneyBR((float) ($cartao->saldo ?? 0));
            $cartao->status_print = $this->statusBadge((string) ($cartao->status ?? ""));
            $cartao->validade_print = !empty($cartao->validade)
                ? format_date((string) $cartao->validade)
                : "-";
            $cartao->abrir_hub = $this->router->route("admin.cartao.hub", ["id" => $cartao->id]);
            $cartao->abrir_editar = $this->router->route("admin.cartao.editar", ["id" => $cartao->id]);
        }

        if ($cliente->id) {
            $cliente->principal_print = trim((string) ($cliente->razao ?: $cliente->nome)) . " <small>(#{$cliente->id})</small>";
        } else {
            $cliente->principal_print = "-";
        }

        $cliente->documento_print = $this->formatDocumento($cliente->pessoa, $cliente->documento);
        $cliente->telefone_print = $this->formatPhone($cliente->telefone ?? null);
        $cliente->situacao_print = !empty($cliente->id_situacao)
            ? $this->badgeColor(
                $cliente->situacao_descricao ?: "Sem situação",
                $cliente->situacao_cor ?: "#6c757d"
            )
            : '<i class="text-black-50">Não definido</i>';

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Grupos de Clientes" => ["url" => $this->router->route("admin.cliente.index"), "current" => false],
                "Cartões do Cliente" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Cartões do Cliente",
                "desc" => "Lista dos cartões vinculados ao cliente selecionado",
            ],
        ]);

        echo $this->view->render("admin/cliente/cartoes", [
            "cliente" => $cliente,
            "cartoes" => $cartoes,
            "total_cartoes" => $totalCartoes,
            "saldo_total" => $saldoTotal,
            "saldo_total_print" => $this->moneyBR($saldoTotal),
            "csrf" => $this->csrf->generate(),
            "permissao" => [
                "cartao_gerenciar" => $this->auth->allow("cartao_gerenciar"),
                "cartao_editar" => $this->auth->allow("cartao_editar"),
            ],
        ]);
    }

    /**
     * Abre o formulario de novo cliente.
     *
     * A view recebe listas auxiliares para montar select de situacao, ramo
     * e as configuracoes de pessoa fixa ou alternavel.
     */
    public function new(): void
    {
        $this->authorize("cliente_inserir");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Grupos de Clientes" => ["url" => $this->router->route("admin.cliente.index"), "current" => false],
                "Novo Grupo" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Novo Grupo de Clientes",
                "desc" => "Informe o nome do grupo",
            ],
        ]);

        $usaRamo = $this->useRamoCliente();

        echo $this->view->render("admin/cliente/novo", [
            "csrf" => $this->csrf->generate(),
            "ramos" => $usaRamo ? ClienteRamo::orderBy("descricao")->get() : [],
            "situacoes" => ClienteSituacao::orderBy("descricao")->get(),
            "url_back" => $this->router->route("admin.cliente.index"),
            "pessoa_padrao" => $this->defaultPessoaCliente(),
            "usa_ramo" => $usaRamo,
            "pessoa_opcoes" => $this->pessoaOpcoesCliente(),
            "pessoa_fixa" => $this->pessoaFixaCliente(),
        ]);
    }

    /**
     * Valida o formulario de novo cliente e grava no banco.
     *
     * A normalizacao pesada acontece em normalizedData(), para manter create()
     * e update() com a mesma regra de limpeza.
     */
    public function create(Request $request): void
    {
        $this->authorize("cliente_inserir");

        $data = $this->normalizedData($request);

        Cliente::create($data);

        $this->message->success("Cliente cadastrado com sucesso");
        $this->router->redirect("admin.cliente.index");
    }

    /**
     * Cria um cliente rapido via AJAX.
     *
     * Este atalho existe para fluxos operacionais como emissao de cartão, onde
     * o operador precisa cadastrar somente o mínimo indispensável e seguir sem
     * abandonar a tela atual.
     */
    public function quickCreate(Request $request): void
    {
        $this->authorize("cliente_inserir");

        $data = new Data($request->all());
        $nome = trim((string) ($data->nome ?? ""));
        $documento = trim((string) ($data->documento ?? ""));
        $telefone = trim((string) ($data->telefone ?? ""));
        $telefoneNormalizado = only_numbers($telefone);
        $pessoa = $this->defaultPessoaCliente();

        if ($nome === "") {
            $this->quickClientJson(false, "Informe o nome do cliente.");
            return;
        }

        if ($telefone === "") {
            $this->quickClientJson(false, "Informe o telefone do cliente.");
            return;
        }

        if (strlen($telefoneNormalizado) < 10 || strlen($telefoneNormalizado) > 11) {
            $this->quickClientJson(false, "Informe um telefone válido com DDD.");
            return;
        }

        $documentoNormalizado = $this->normalizeImportDocumento($documento);
        if ($documentoNormalizado !== "") {
            if (strlen($documentoNormalizado) === 11) {
                if (function_exists("validaCPF") && !validaCPF($documentoNormalizado)) {
                    $this->quickClientJson(false, "Documento inválido. Informe um CPF válido.");
                    return;
                }

                $pessoa = "F";
            } elseif (strlen($documentoNormalizado) === 14) {
                if (function_exists("validaCNPJ") && !validaCNPJ($documentoNormalizado)) {
                    $this->quickClientJson(false, "Documento inválido. Informe um CNPJ válido.");
                    return;
                }

                $pessoa = "J";
            } else {
                $this->quickClientJson(false, "Documento inválido. Informe CPF ou CNPJ válido.");
                return;
            }

            if (Cliente::where("documento", "=", $documentoNormalizado)->count() > 0) {
                $this->quickClientJson(false, "Já existe um cliente com este documento.");
                return;
            }
        }

        $payload = [
            "pessoa" => $pessoa,
            "razao" => $nome,
            "nome" => null,
            "documento" => $documentoNormalizado ?: null,
            "telefone" => $telefoneNormalizado,
            "id_situacao" => null,
            "trash" => 0,
            "created_by" => $this->user->uid,
        ];

        $result = Cliente::create($payload);
        $cliente = Cliente::find($result->id) ?? (object) array_merge($payload, [
            "id" => $result->id,
        ]);

        $this->quickClientJson(true, "Cliente cadastrado com sucesso.", [
            "id" => $cliente->id,
            "label" => $this->quickClientLabel($cliente),
        ]);
    }

    /**
     * Abre o formulario de edicao com o registro selecionado.
     *
     * O cliente pode ser localizado tanto por id normal quanto por hash md5,
     * porque algumas telas trafegam o identificador compactado.
     */
    public function edit(Request $request): void
    {
        $this->authorize("cliente_editar");

        $data = new Data($request->all());
        $cliente = Cliente::find($data->id) ?: Cliente::findByMd5($data->id);

        if (!$cliente) {
            $this->message->warning("Cliente não encontrado");
            $this->router->redirect("admin.cliente.index");
        }

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Grupos de Clientes" => ["url" => $this->router->route("admin.cliente.index"), "current" => false],
                "Editar Grupo" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Editar Grupo de Clientes",
                "desc" => "Atualize o nome do grupo",
            ],
        ]);

        $usaRamo = $this->useRamoCliente();

        $empresas = Empresa::select("e.*")
            ->where("e.id_cliente", "=", (int) $cliente->id)
            ->orderBy("e.razao")
            ->get();

        foreach ($empresas as $empresa) {
            $empresa->documento_print = $this->formatDocumento($empresa->pessoa, $empresa->documento);
            $empresa->editar_url = $this->router->route("admin.empresa.editar", ["id" => $empresa->id]);
        }

        echo $this->view->render("admin/cliente/editar", [
            "csrf" => $this->csrf->generate(),
            "cliente" => $cliente,
            "empresas" => $empresas,
            "ramos" => $usaRamo ? ClienteRamo::orderBy("descricao")->get() : [],
            "situacoes" => ClienteSituacao::orderBy("descricao")->get(),
            "url_back" => $this->router->route("admin.cliente.index"),
            "usa_ramo" => $usaRamo,
            "pessoa_opcoes" => $this->pessoaOpcoesCliente(),
            "pessoa_fixa" => $this->pessoaFixaCliente(),
        ]);
    }

    /**
     * Valida o formulario de edicao e sobrescreve os dados do cliente.
     */
    public function update(Request $request): void
    {
        $this->authorize("cliente_editar");

        $data = new Data($request->all());
        $cliente = Cliente::find($data->id) ?: Cliente::findByMd5($data->id);

        if (!$cliente) {
            $this->message->warning("Cliente não encontrado");
            Redirect::referer();
            return;
        }

        $payload = $this->normalizedData($request, true);
        Cliente::updateBy($cliente->id, $payload);

        $this->message->success("Cliente atualizado com sucesso");
        $this->router->redirect("admin.cliente.editar", ["id" => $cliente->hash()]);
    }

    /**
     * Faz exclusao logica do cliente.
     *
     * O registro nao e apagado fisicamente; recebe trash = 1 para manter
     * historico e permitir eventuais recuperacoes futuras.
     */
    public function delete(Request $request): void
    {
        $this->authorize("cliente_excluir");

        $data = new Data($request->all());
        $cliente = Cliente::findByMd5($data->id);

        if (!$cliente) {
            $this->message->warning("Cliente não encontrado");
            Redirect::referer();
        }

        Cliente::updateBy($cliente->id, [
            "trash" => 1,
            "deleted_by" => $this->user->uid,
            "deleted_at" => date("Y-m-d H:i:s"),
        ]);

        $this->message->success("Cliente removido com sucesso");
        Redirect::referer();
    }

    /**
     * Consulta CNPJ na API externa e devolve um payload pronto para preencher
     * o formulario de cliente.
     *
     * O retorno diferencia CNPJ numerico e alfanumerico, porque a consulta
     * automatica ainda so cobre o formato numerico.
     */
    public function find(Request $request): void
    {
        $data = new Data($request->all());
        $cnpj = $this->normalizeImportDocumento($data->cnpj ?? $data->documento ?? "");

        header("Content-Type: application/json; charset=utf-8");

        if (strlen($cnpj) !== 14) {
            echo json_encode([
                "error" => true,
                "message" => "Informe um CNPJ válido.",
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (preg_match('/[A-Z]/', $cnpj)) {
            echo json_encode([
                "error" => true,
                "message" => "CNPJ alfanumerico validado, mas a consulta automática ainda não está disponível.",
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $info = (new Cnpj())->get($cnpj);
        } catch (\Throwable $e) {
            echo json_encode([
                "error" => true,
                "message" => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            "error" => false,
            "message" => "CNPJ localizado com sucesso.",
            "data" => [
                "pessoa" => "J",
                "documento" => $info["cnpj"] ?? $cnpj,
                "razao" => $info["nome"] ?? "",
                "nome" => $info["fantasia"] ?? ($info["nome"] ?? ""),
                "nascimento" => $info["abertura"] ?? "",
                "contato" => $info["responsavel"] ?? "",
                "telefone" => $info["telefone"] ?? "",
                "email" => $info["email"] ?? "",
                "site" => $info["site"] ?? "",
                "cep" => $info["cep"] ?? "",
                "endereco" => $info["logradouro"] ?? "",
                "numero" => $info["numero"] ?? "",
                "complemento" => $info["complemento"] ?? "",
                "bairro" => $info["bairro"] ?? "",
                "cidade" => $info["municipio"] ?? "",
                "estado" => $info["uf"] ?? "",
                "pais" => "Brasil",
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Mantem rota legada de servico ativa enquanto a base não migra tudo.
     */
    public function updateServico(Request $request): void
    {
        $this->message->warning("Funcionalidade de serviço ainda não foi migrada para a base.");
        Redirect::referer();
    }

    /**
     * Mantem rota legada de historico ativa para não quebrar telas antigas.
     */
    public function historicoServico(Request $request): void
    {
        $this->message->warning("Funcionalidade de histórico de serviço ainda não foi migrada para a base.");
        Redirect::referer();
    }

    /**
     * Mantem a acao legada de empresa apenas como compatibilidade.
     */
    public function editEmpresa(Request $request): void
    {
        $this->message->warning("Recurso legado não está em uso nesta base.");
        Redirect::referer();
    }

    /**
     * Mantem a acao legada de bloqueio como aviso ate a migracao completa.
     */
    public function bloquear(Request $request): void
    {
        $this->message->warning("Recurso de bloqueio ainda não foi migrado para a base.");
        Redirect::referer();
    }

    /**
     * Recebe a planilha enviada pelo usuario e dispara o fluxo de importacao.
     *
     * Essa acao apenas valida, salva o arquivo temporario e chama o
     * processador principal. O resumo final volta em flash do tipo alert.
     */
    public function importUpload(Request $request): void
    {
        $this->authorize("cliente_inserir");
        // O modal permite ao gestor decidir se a importacao pode seguir
        // mesmo quando a planilha vier com poucos campos preenchidos.
        $allowIncomplete = !empty($_POST["permitir_incompletos"]);

        if (empty($_FILES["arquivo"]["tmp_name"]) || empty($_FILES["arquivo"]["name"])) {
            $this->message->flash("Selecione uma planilha para importar", "warning", "exclamation-triangle", "alert");
            Redirect::referer();
            return;
        }

        $file = $_FILES["arquivo"];
        $extension = strtolower(pathinfo((string) $file["name"], PATHINFO_EXTENSION));

        if (!in_array($extension, ["xlsx", "xls", "csv"], true)) {
            $this->message->flash("Envie uma planilha no formato XLSX, XLS ou CSV", "warning", "exclamation-triangle", "alert");
            Redirect::referer();
            return;
        }

        $dir = base_path("storage/tmp/clientes/importacoes");
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo((string) $file["name"], PATHINFO_FILENAME));
        $target = $dir . DIRECTORY_SEPARATOR . $safeName . "." . $extension;

        if (!move_uploaded_file($file["tmp_name"], $target)) {
            $this->message->flash("Não foi possivel salvar a planilha enviada", "warning", "exclamation-triangle", "alert");
            Redirect::referer();
            return;
        }

        try {
            $summary = $this->processClienteImport($target, $allowIncomplete);
            $message = $this->buildImportResultAlert($summary);
            $hasIssues = (int) ($summary["issues_count"] ?? 0) > 0;
            $this->message->flash(
                $message,
                $hasIssues ? "warning" : "success",
                $hasIssues ? "exclamation-triangle" : "check-circle",
                "alert"
            );
        } catch (\Throwable $e) {
            $this->message->flash("Não foi possivel importar a planilha: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8"), "danger", "exclamation-circle", "alert");
        }

        $this->router->redirect("admin.cliente.index");
        return;

        $dir = "storage/tmp/clientes/importacoes/";
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo((string) $file["name"], PATHINFO_FILENAME));
        $target = $dir . $safeName . "." . $extension;

        if (!move_uploaded_file($file["tmp_name"], $target)) {
            $this->message->warning("Não foi possível salvar a planilha enviada");
            Redirect::referer();
            return;
        }

        $this->message->success("Planilha recebida com sucesso. O processamento da importação será o próximo passo.");
        $this->router->redirect("admin.cliente.index");
    }

    /**
     * Processa a planilha de clientes em lotes para evitar estourar memoria.
     *
     * O fluxo:
     * - le os cabecalhos e mapeia colunas;
     * - carrega cache de situacoes e ramos;
     * - identifica duplicados por documento;
     * - monta inserts em blocos;
     * - insere usando insertMany().
     *
     * @param string $path Caminho do arquivo enviado.
     * @param bool $allowIncomplete Permite linhas com dados minimos.
     * @return array{imported:int,skipped:int,created_ramos:int,issues:array,issues_count:int}
     */
    private function processClienteImport(string $path, bool $allowIncomplete = false): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $sheetInfo = $reader->listWorksheetInfo($path);
        if (empty($sheetInfo[0]['totalRows'])) {
            throw new \RuntimeException("A planilha enviada está vazia.");
        }

        $totalRows = (int) $sheetInfo[0]['totalRows'];
        $totalColumns = (int) ($sheetInfo[0]['totalColumns'] ?? 0);
        $lastColumn = Coordinate::stringFromColumnIndex(max(1, $totalColumns ?: 22));

        $headers = $this->readImportHeaders($reader, $path, $lastColumn);
        $columns = $this->mapImportColumns($headers);

        if ((!$allowIncomplete) && (empty($columns['pessoa']) || empty($columns['documento']))) {
            throw new \RuntimeException("A planilha não contém as colunas obrigatórias de Pessoa e Documento.");
        }

        // Cache em memoria para resolver situacoes sem bater no banco a cada linha.
        $situacoes = ClienteSituacao::orderBy("descricao")->get();
        $situacaoCache = [];
        foreach ($situacoes as $situacao) {
            $situacaoCache[$this->normalizeImportKey($situacao->descricao)] = (int) $situacao->id;
        }

        // Nao criamos situacoes padrao aqui porque esse cadastro pode variar
        // de sistema para sistema. A importacao usa o que ja existir e cria
        // apenas o que vier explicitamente na planilha.

        // Cache separado para ramo, com a mesma ideia de reaproveitar ids
        // ja existentes e criar apenas o que realmente faltar.
        $ramos = ClienteRamo::orderBy("descricao")->get();
        $ramoCache = [];
        foreach ($ramos as $ramo) {
            $ramoCache[$this->normalizeImportKey($ramo->descricao)] = (int) $ramo->id;
        }

        // Documentos ja cadastrados na base entram no cache de duplicidade
        // para que a planilha nao crie registros repetidos.
        $existingDocuments = [];
        foreach (Cliente::select("documento")->get() as $cliente) {
            $documento = $this->normalizeImportDocumento($cliente->documento ?? "");
            if ($documento !== "") {
                $existingDocuments[$documento] = true;
            }
        }

        $imported = 0;
        $skipped = 0;
        $createdRamos = 0;
        $issues = [];
        $seenDocuments = [];
        $chunkSize = 300;
        $insertColumns = [
            "id_ramo",
            "id_situacao",
            "pessoa",
            "documento",
            "rg_ie",
            "razao",
            "nome",
            "nascimento",
            "contato",
            "telefone",
            "whatsapp",
            "email",
            "site",
            "cep",
            "endereco",
            "numero",
            "complemento",
            "bairro",
            "cidade",
            "estado",
            "pais",
            "observacoes",
            "trash",
            "created_by",
        ];

        for ($startRow = 2; $startRow <= $totalRows; $startRow += $chunkSize) {
            $endRow = min($totalRows, $startRow + $chunkSize - 1);
            $chunkRows = $this->readImportChunk($reader, $path, $startRow, $endRow, $lastColumn);
            $chunkInsertRows = [];

            foreach ($chunkRows as $rowNumber => $row) {
                $line = (int) $rowNumber;
                $rowDataResult = $this->buildImportRow($row, $columns, $situacaoCache, $line, $allowIncomplete);

                if (!empty($rowDataResult['error'])) {
                    $skipped++;
                    $issues[] = [
                        "line" => $line,
                        "type" => "error",
                        "message" => $rowDataResult['error'],
                    ];
                    continue;
                }

                $rowData = $rowDataResult['data'];

                if (!empty($rowData['documento'])) {
                    // Duplicidade e verificada por documento normalizado,
                    // tanto contra o banco quanto contra a propria planilha.
                    if (isset($existingDocuments[$rowData['documento']]) || isset($seenDocuments[$rowData['documento']])) {
                        $skipped++;
                        $issues[] = [
                            "line" => $line,
                            "type" => "duplicate",
                            "message" => "Cliente duplicado. Documento {$rowData['documento']} já existe.",
                        ];
                        continue;
                    }

                    $seenDocuments[$rowData['documento']] = true;
                }

                if (!empty($rowData['ramo_descricao'])) {
                    // Ramo nao fica no payload final como texto; ele vira id
                    // e, se nao existir, e criado e reaproveitado nas proximas linhas.
                    $ramoCriado = false;
                    $ramoId = $this->resolveImportRamoId($rowData['ramo_descricao'], $ramoCache, $ramoCriado);
                    if ($ramoId && empty($rowData['id_ramo'])) {
                        $rowData['id_ramo'] = $ramoId;
                    }

                    if ($ramoCriado) {
                        $createdRamos++;
                    }
                }

                // Antes do insertMany, reforcamos upper e normalizacao final.
                $rowData = $this->normalizeImportInsertRow($rowData);
                $rowData['created_by'] = $this->user->uid;
                $rowData['trash'] = 0;
                unset($rowData['ramo_descricao']);

                $chunkInsertRows[] = $rowData;
            }

            if ($chunkInsertRows) {
                // Insert em bloco reduz custo de query e melhora o desempenho
                // em planilhas maiores.
                Cliente::insertMany($insertColumns, $chunkInsertRows, 300);
                $imported += count($chunkInsertRows);
            }
        }

        if (!$imported) {
            return [
                "imported" => 0,
                "skipped" => $skipped,
                "created_ramos" => $createdRamos,
                "issues" => $issues,
                "issues_count" => count($issues),
            ];
        }

        return [
            "imported" => $imported,
            "skipped" => $skipped,
            "created_ramos" => $createdRamos,
            "issues" => $issues,
            "issues_count" => count($issues),
        ];
    }

    /**
     * Le apenas o cabecalho da planilha para mapear as colunas por nome.
     */
    private function readImportHeaders($reader, string $path, string $lastColumn): array
    {
        $filter = new class(1, 1, Coordinate::columnIndexFromString($lastColumn)) implements IReadFilter {
            private int $startRow;
            private int $endRow;
            private int $maxColumn;

            public function __construct(int $startRow, int $chunkSize, int $maxColumn)
            {
                $this->startRow = $startRow;
                $this->endRow = $startRow + $chunkSize - 1;
                $this->maxColumn = $maxColumn;
            }

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row >= $this->startRow
                    && $row <= $this->endRow
                    && Coordinate::columnIndexFromString($columnAddress) <= $this->maxColumn;
            }
        };

        $reader->setReadFilter($filter);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $headers = $sheet->rangeToArray('A1:' . $lastColumn . '1', null, true, false, true);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $headers[1] ?? [];
    }

    /**
     * Le apenas um bloco de linhas da planilha usando filtro de leitura.
     *
     * Isso evita carregar o arquivo inteiro em memoria, o que e importante
     * quando a importacao vem com muitas linhas.
     */
    private function readImportChunk($reader, string $path, int $startRow, int $endRow, string $lastColumn): array
    {
        $filter = new class($startRow, $endRow, Coordinate::columnIndexFromString($lastColumn)) implements IReadFilter {
            private int $startRow;
            private int $endRow;
            private int $maxColumn;

            public function __construct(int $startRow, int $endRow, int $maxColumn)
            {
                $this->startRow = $startRow;
                $this->endRow = $endRow;
                $this->maxColumn = $maxColumn;
            }

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row >= $this->startRow
                    && $row <= $this->endRow
                    && Coordinate::columnIndexFromString($columnAddress) <= $this->maxColumn;
            }
        };

        $reader->setReadFilter($filter);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $range = 'A' . $startRow . ':' . $lastColumn . $endRow;
        $rows = $sheet->rangeToArray($range, null, true, false, true);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    /**
     * Mapeia cabecalhos amigaveis e posicoes fixas da planilha para chaves internas.
     *
     * O mapeamento por alias tenta reconhecer nomes parecidos; o fallback por
     * coluna protege o modelo padrao quando o arquivo vem no layout esperado.
     */
    private function mapImportColumns(array $headers): array
    {
        $mapped = [];
        $normalized = [];

        foreach ($headers as $column => $label) {
            $normalized[$this->normalizeImportKey((string) $label)] = $column;
        }

        $aliases = [
            "pessoa" => ["TIPO DE PESSOA", "PESSOA", "TIPO PESSOA"],
            "documento" => ["CPF CNPJ", "CPF OU CNPJ", "CPF", "CNPJ"],
            "razao" => ["RAZAO SOCIAL / NOME COMPLETO", "RAZAO SOCIAL NOME COMPLETO", "RAZAO SOCIAL", "NOME COMPLETO", "RAZAO", "NOME"],
            "nome" => ["NOME FANTASIA / APELIDO", "NOME FANTASIA APELIDO", "NOME FANTASIA", "APELIDO"],
            "rg_ie" => ["IE / RG", "IE RG", "RG IE", "RG", "IE"],
            "nascimento" => ["DATA DE ABERTURA / NASCIMENTO", "DATA DE ABERTURA NASCIMENTO", "DATA DE ABERTURA", "NASCIMENTO", "ABERTURA"],
            "contato" => ["CONTATO"],
            "telefone" => ["TELEFONE", "CELULAR"],
            "whatsapp" => ["WHATSAPP"],
            "email" => ["E-MAIL", "E MAIL", "EMAIL"],
            "site" => ["SITE", "WEBSITE"],
            "situacao_descricao" => ["SITUACAO"],
            "ramo_descricao" => ["RAMO"],
            "cep" => ["CEP"],
            "endereco" => ["LOGRADOURO", "ENDERECO"],
            "numero" => ["NUMERO", "N"],
            "complemento" => ["COMPLEMENTO"],
            "bairro" => ["BAIRRO"],
            "cidade" => ["CIDADE"],
            "estado" => ["ESTADO", "UF"],
            "pais" => ["PAIS"],
            "observacoes" => ["OBSERVACOES"],
        ];

        foreach ($aliases as $key => $possibleHeaders) {
            foreach ($possibleHeaders as $alias) {
                $aliasKey = $this->normalizeImportKey($alias);
                if (isset($normalized[$aliasKey])) {
                    $mapped[$key] = $normalized[$aliasKey];
                    break;
                }
            }
        }

        $hasRamoColumn = $this->useRamoCliente();

        $fallbacks = $hasRamoColumn
            ? [
                "pessoa" => "A",
                "documento" => "B",
                "razao" => "C",
                "nome" => "D",
                "rg_ie" => "E",
                "nascimento" => "F",
                "contato" => "G",
                "telefone" => "H",
                "whatsapp" => "I",
                "email" => "J",
                "site" => "K",
                "situacao_descricao" => "L",
                "ramo_descricao" => "M",
                "cep" => "N",
                "endereco" => "O",
                "numero" => "P",
                "complemento" => "Q",
                "bairro" => "R",
                "cidade" => "S",
                "estado" => "T",
                "pais" => "U",
                "observacoes" => "V",
            ]
            : [
                "pessoa" => "A",
                "documento" => "B",
                "razao" => "C",
                "nome" => "D",
                "rg_ie" => "E",
                "nascimento" => "F",
                "contato" => "G",
                "telefone" => "H",
                "whatsapp" => "I",
                "email" => "J",
                "site" => "K",
                "situacao_descricao" => "L",
                "cep" => "M",
                "endereco" => "N",
                "numero" => "O",
                "complemento" => "P",
                "bairro" => "Q",
                "cidade" => "R",
                "estado" => "S",
                "pais" => "T",
                "observacoes" => "U",
            ];

        foreach ($fallbacks as $key => $column) {
            if (!isset($mapped[$key]) && isset($headers[$column])) {
                $mapped[$key] = $column;
            }
        }

        return $mapped;
    }

    /**
     * Converte uma linha crua da planilha em payload pronto para insert.
     *
     * Aqui entram as regras mais importantes da importacao:
     * - reconhecimento de PF/PJ;
     * - validacao real de CPF/CNPJ;
     * - limpeza de telefone, whatsapp e CEP;
     * - resolucao de situacao e ramo;
     * - permissao para linhas incompletas quando o gestor habilita a opcao.
     */
    private function buildImportRow(array $row, array $columns, array &$situacaoCache, int $line = 0, bool $allowIncomplete = false): array
    {
        $cell = function (string $key) use ($row, $columns) {
            return isset($columns[$key]) ? ($row[$columns[$key]] ?? null) : null;
        };

        $pessoaValue = isset($columns['pessoa']) ? ($row[$columns['pessoa']] ?? null) : null;
        $documentoValue = isset($columns['documento']) ? ($row[$columns['documento']] ?? null) : null;
        $razaoValue = $cell('razao');
        $nomeValue = $cell('nome');
        $ramoValue = $cell('ramo_descricao');

        $pessoa = $this->parseImportPessoa($pessoaValue, $documentoValue);
        $documento = $this->normalizeImportDocumento($documentoValue);
        $razao = $this->normalizeImportFieldValue('razao', $razaoValue);
        $nome = $this->normalizeImportFieldValue('nome', $nomeValue);
        $ramoDescricao = !empty($columns['ramo_descricao'])
            ? $this->normalizeImportFieldValue('ramo_descricao', $ramoValue)
            : "";

        if (!$allowIncomplete && ($documento === "" || ($razao === "" && $nome === ""))) {
            return [
                "error" => "Linha {$line}: informe Documento e pelo menos Razão Social ou Nome.",
            ];
        }

        if ($allowIncomplete && $documento === "" && $razao === "" && $nome === "") {
            // No modo flexivel, nao inventamos identificacao; a linha segue
            // com os campos vazios para revisao posterior.
            $razao = "";
            $nome = "";
        }

        $documentoLength = strlen($documento);
        if ($documento !== "" && $pessoa === "J" && $documentoLength !== 14) {
            return [
                "error" => "Linha {$line}: CNPJ deve ter 14 dígitos.",
            ];
        }

        if ($documento !== "" && $pessoa === "F" && $documentoLength !== 11) {
            return [
                "error" => "Linha {$line}: CPF deve ter 11 dígitos.",
            ];
        }

        $cnpjValidator = function_exists("validaCNPJBR") ? "validaCNPJBR" : "validaCNPJ";

        if ($documento !== "" && $pessoa === "J" && function_exists($cnpjValidator) && !$cnpjValidator($documento)) {
            return [
                "error" => "Linha {$line}: CNPJ inválido.",
            ];
        }

        if ($documento !== "" && $pessoa === "F" && function_exists("validaCPF") && !validaCPF($documento)) {
            return [
                "error" => "Linha {$line}: CPF inválido.",
            ];
        }

        if ($allowIncomplete) {
            $razao = $razao ?: $nome ?: "";
            $nome = $nome ?: "";
        } else {
            $razao = $razao ?: $nome ?: $documento;
            $nome = $nome ?: $razao;
        }

        return [
            "data" => [
                "id_situacao" => $this->resolveImportSituacaoId($cell('situacao_descricao'), $situacaoCache),
                "id_ramo" => null,
                "pessoa" => $pessoa,
                "documento" => $documento ?: ($allowIncomplete ? "" : null),
                "rg_ie" => $this->normalizeImportFieldValue('rg_ie', $cell('rg_ie')),
                "razao" => $razao ?: ($allowIncomplete ? "" : null),
                "nome" => $nome ?: ($allowIncomplete ? "" : null),
                "nascimento" => $this->parseImportDate($cell('nascimento')),
                "contato" => $this->normalizeImportFieldValue('contato', $cell('contato')),
                "telefone" => only_numbers((string) $cell('telefone')),
                "whatsapp" => only_numbers((string) $cell('whatsapp')),
                "email" => $this->normalizeImportFieldValue('email', $cell('email')),
                "site" => $this->normalizeImportFieldValue('site', $cell('site')),
                "cep" => only_numbers((string) $cell('cep')),
                "endereco" => $this->normalizeImportFieldValue('endereco', $cell('endereco')),
                "numero" => $this->normalizeImportFieldValue('numero', $cell('numero')),
                "complemento" => $this->normalizeImportFieldValue('complemento', $cell('complemento')),
                "bairro" => $this->normalizeImportFieldValue('bairro', $cell('bairro')),
                "cidade" => $this->normalizeImportFieldValue('cidade', $cell('cidade')),
                "estado" => $this->normalizeImportText($cell('estado')),
                "pais" => $this->normalizeImportFieldValue('pais', $cell('pais')) ?: "Brasil",
                "observacoes" => $this->normalizeImportFieldValue('observacoes', $cell('observacoes')),
                "ramo_descricao" => $ramoDescricao,
            ],
        ];
    }

    /**
     * Monta o resumo final exibido ao usuario apos a importacao.
     *
     * O retorno mistura sucesso e ocorrencias para que o gestor veja
     * imediatamente o que entrou, o que foi ignorado e por que.
     */
    private function buildImportResultAlert(array $summary): string
    {
        $imported = (int) ($summary["imported"] ?? 0);
        $skipped = (int) ($summary["skipped"] ?? 0);
        $createdRamos = (int) ($summary["created_ramos"] ?? 0);
        $issues = is_array($summary["issues"] ?? null) ? $summary["issues"] : [];

        $html = [];
        $html[] = '<div class="mb-1"><strong>Importação concluída.</strong></div>';
        $html[] = '<div class="mb-1">' . $imported . ' cliente(s) importado(s).</div>';

        if ($skipped > 0) {
            $html[] = '<div class="mb-1">' . $skipped . ' linha(s) ignorada(s).</div>';
        }

        if ($createdRamos > 0) {
            $html[] = '<div class="mb-1">' . $createdRamos . ' ramo(s) criado(s).</div>';
        }

        if (!empty($issues)) {
            $html[] = '<div class="mb-1"><strong>Ocorrências:</strong></div>';
            $html[] = '<ul class="mb-0 ps-3">';

            $limit = 20;
            foreach (array_slice($issues, 0, $limit) as $issue) {
                $line = (int) ($issue["line"] ?? 0);
                $message = (string) ($issue["message"] ?? "Erro ao processar a linha.");
                $message = preg_replace('/^Linha\\s+\\d+\\s*:\\s*/i', '', $message) ?: $message;
                $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
                $html[] = '<li>Linha ' . $line . ': ' . $message . '</li>';
            }

            if (count($issues) > $limit) {
                $html[] = '<li>... e mais ' . (count($issues) - $limit) . ' ocorrência(s).</li>';
            }

            $html[] = '</ul>';
        }

        return implode('', $html);
    }

    /**
     * Resolve a situacao da planilha.
     *
     * O fluxo tenta primeiro reaproveitar uma situacao ja existente. Se nao
     * houver correspondencia segura, a descricao enviada pela planilha e
     * cadastrada e o novo id e reutilizado nas proximas linhas.
     */
    private function resolveImportSituacaoId(mixed $value, array &$cache): ?int
    {
        $label = $this->normalizeImportKey((string) $value);

        if ($label === "") {
            return null;
        }

        if (isset($cache[$label])) {
            return $cache[$label];
        }

        if (str_contains($label, "INAT")) {
            foreach ($cache as $key => $id) {
                if (str_contains($key, "INAT")) {
                    return $id;
                }
            }
        }

        if (str_contains($label, "ATIV")) {
            foreach ($cache as $key => $id) {
                if (str_contains($key, "ATIV")) {
                    return $id;
                }
            }
        }

        $bestId = null;
        $bestScore = 0;
        foreach ($cache as $key => $id) {
            similar_text($label, $key, $score);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $id;
            }
        }

        if ($bestScore >= 80 && $bestId) {
            return $bestId;
        }

        $descricao = trim((string) $value);
        if ($descricao === "") {
            return null;
        }

        $situacao = ClienteSituacao::create([
            "descricao" => $descricao,
            "cor" => "#000000",
            "ativo" => 1,
            "created_by" => $this->user->uid,
        ]);

        $cache[$label] = (int) $situacao->id;
        return (int) $situacao->id;
    }

    /**
     * Resolve o ramo da planilha e cria o cadastro caso ele ainda nao exista.
     */
    private function resolveImportRamoId(string $descricao, array &$cache, bool &$created = false): ?int
    {
        $descricao = trim($descricao);
        if ($descricao === "") {
            return null;
        }

        $key = $this->normalizeImportKey($descricao);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $bestId = null;
        $bestScore = 0;
        foreach ($cache as $cachedKey => $id) {
            similar_text($key, $cachedKey, $score);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = $id;
            }
        }

        if ($bestScore >= 85 && $bestId) {
            return $bestId;
        }

        $created = true;
        $ramo = ClienteRamo::create([
            "descricao" => $descricao,
            "created_by" => $this->user->uid,
        ]);

        $cache[$key] = (int) $ramo->id;
        return (int) $ramo->id;
    }

    /**
     * Normaliza um texto livre para comparacao e exibicao interna.
     *
     * Esta rotina e usada em labels, situacoes, ramos e outros textos onde
     * queremos padronizar espacos e remover excesso de variacoes.
     */
    private function normalizeImportText(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format("Y-m-d");
        }

        $text = trim((string) $value);
        if ($text === "") {
            return "";
        }

        $text = preg_replace('/\s+/u', ' ', $text);
        return $this->toUpper($text);
    }

    /**
     * Prepara e-mail para armazenamento sem mudar a intencao original.
     */
    private function normalizeImportEmail(mixed $value): string
    {
        $text = trim((string) $value);
        return $text !== "" ? strtolower($text) : "";
    }

    /**
     * Remove mascara do documento preservando letras para o CNPJ novo.
     */
    private function normalizeImportDocumento(mixed $value): string
    {
        $text = strtoupper(trim((string) $value));
        if ($text === "") {
            return "";
        }

        return preg_replace('/[^A-Za-z0-9]/', '', $text) ?: "";
    }

    /**
     * Aplica upper somente nos campos que o model marcou como padrao.
     *
     * Isso garante que a importacao respeite a regra centralizada no model
     * e nao force maiusculo em campos livres da planilha.
     */
    private function normalizeImportFieldValue(string $field, mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === "") {
            return "";
        }

        if (in_array($field, Cliente::getUppers(), true)) {
            return $this->toUpper($text);
        }

        return $text;
    }

    /**
     * Reforca a normalizacao final antes do insertMany.
     *
     * O insertMany nao passa pelo create() do Model, entao esta etapa
     * reaplica a regra de upper e a conversao final de estado.
     */
    private function normalizeImportInsertRow(array $row): array
    {
        foreach (Cliente::getUppers() as $field) {
            if (!array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
                continue;
            }

            $row[$field] = $this->toUpper((string) $row[$field]);
        }

        $row['estado'] = $this->normalizeImportEstado($row['estado'] ?? null);

        return $row;
    }

    /**
     * Converte nome completo ou sigla de estado para UF valida.
     *
     * A planilha pode vir com "Sao Paulo" ou "SP"; aqui unificamos os dois.
     */
    private function normalizeImportEstado(mixed $value): string
    {
        $text = $this->normalizeImportKey((string) $value);

        if ($text === '') {
            return '';
        }

        $ufMap = [
            'ACRE' => 'AC',
            'ALAGOAS' => 'AL',
            'AMAPA' => 'AP',
            'AMAZONAS' => 'AM',
            'BAHIA' => 'BA',
            'CEARA' => 'CE',
            'DISTRITO FEDERAL' => 'DF',
            'ESPIRITO SANTO' => 'ES',
            'GOIAS' => 'GO',
            'MARANHAO' => 'MA',
            'MATO GROSSO' => 'MT',
            'MATO GROSSO DO SUL' => 'MS',
            'MINAS GERAIS' => 'MG',
            'PARA' => 'PA',
            'PARAIBA' => 'PB',
            'PARANA' => 'PR',
            'PERNAMBUCO' => 'PE',
            'PIAUI' => 'PI',
            'RIO DE JANEIRO' => 'RJ',
            'RIO GRANDE DO NORTE' => 'RN',
            'RIO GRANDE DO SUL' => 'RS',
            'RONDONIA' => 'RO',
            'RORAIMA' => 'RR',
            'SANTA CATARINA' => 'SC',
            'SAO PAULO' => 'SP',
            'SERGIPE' => 'SE',
            'TOCANTINS' => 'TO',
        ];

        if (isset($ufMap[$text])) {
            return $ufMap[$text];
        }

        return in_array($text, $this->estadosBrasil(), true) ? $text : '';
    }

    /**
     * Retorna a lista oficial de UFs do enum compartilhado pelo sistema.
     */
    private function estadosBrasil(): array
    {
        return array_map(
            static fn (EstadoBrasileiro $estado) => $estado->value,
            EstadoBrasileiro::cases()
        );
    }

    /**
     * Gera uma chave comparavel para matching de cabecalhos e descricoes.
     *
     * Remove acentos, pontuacao e espacos extras para facilitar a busca por
     * alias e por nomes parecidos.
     */
    private function normalizeImportKey(string $value): string
    {
        $value = trim($value);
        if ($value === "") {
            return "";
        }

        $value = preg_replace('/\s+/u', ' ', $value);
        $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^A-Za-z0-9]+/', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim($value);

        return $this->toUpper($value);
    }

    /**
     * Sobe texto com suporte a multibyte quando disponivel.
     *
     * Mantemos esse helper central para nao repetir a mesma checagem em
     * varios pontos da importacao.
     */
    private function toUpper(string $value): string
    {
        return function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
    }

    /**
     * Determina se a linha representa PF ou PJ.
     *
     * A regra tenta primeiro ler o valor explicitado na planilha e, se nao
     * houver, cai para a inferencia pelo tamanho do documento.
     */
    private function parseImportPessoa(mixed $value, ?string $documento = null): string
    {
        $label = $this->normalizeImportKey((string) $value);
        $digits = only_numbers((string) $documento);

        $juridica = [
            "J",
            "PJ",
            "JURIDICA",
            "PESSOA JURIDICA",
            "JURIDICO",
            "EMPRESA",
        ];

        $fisica = [
            "F",
            "PF",
            "FISICA",
            "PESSOA FISICA",
        ];

        if ($label !== "") {
            foreach ($juridica as $needle) {
                if ($label === $needle || str_contains($label, $needle)) {
                    return "J";
                }
            }

            foreach ($fisica as $needle) {
                if ($label === $needle || str_contains($label, $needle)) {
                    return "F";
                }
            }
        }

        if (strlen($digits) === 14) {
            return "J";
        }

        return "F";
    }

    /**
     * Converte datas da planilha para o formato persistido no banco.
     */
    private function parseImportDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format("Y-m-d");
        }

        if (is_numeric($value) && (float) $value > 0) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format("Y-m-d");
            } catch (\Throwable $e) {
                return null;
            }
        }

        $text = trim((string) $value);
        if ($text === "") {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return $text;
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $text)) {
            return format_date($text);
        }

        $timestamp = strtotime($text);
        return $timestamp ? date("Y-m-d", $timestamp) : null;
    }

    /**
     * Normaliza os dados vindos do formulario antes do create/update.
     *
     * Esta rotina concentra a limpeza do fluxo manual, espelhando a mesma
     * disciplina usada na importacao.
     */
    private function normalizedData(Request $request, bool $update = false): array
    {
        $data = new Data($request->all());

        $data->nullIfEmpty("id_ramo");
        $data->nullIfEmpty("id_situacao");
        $data->nullIfEmpty("razao");
        $data->nullIfEmpty("nome");
        $data->nullIfEmpty("rg_ie");
        $data->nullIfEmpty("nascimento");
        $data->nullIfEmpty("contato");
        $data->nullIfEmpty("telefone");
        $data->nullIfEmpty("whatsapp");
        $data->nullIfEmpty("email");
        $data->nullIfEmpty("site");
        $data->nullIfEmpty("cep");
        $data->nullIfEmpty("endereco");
        $data->nullIfEmpty("numero");
        $data->nullIfEmpty("complemento");
        $data->nullIfEmpty("bairro");
        $data->nullIfEmpty("cidade");
        $data->nullIfEmpty("estado");
        $data->nullIfEmpty("pais");
        $data->nullIfEmpty("observacoes");

        $payload = $data->all();
        $payload["documento"] = $this->normalizeImportDocumento($payload["documento"] ?? "");
        $payload["cep"] = only_numbers((string) ($payload["cep"] ?? ""));
        $payload["telefone"] = only_numbers((string) ($payload["telefone"] ?? ""));
        $payload["whatsapp"] = only_numbers((string) ($payload["whatsapp"] ?? ""));

        if (!empty($payload["nascimento"])) {
            $payload["nascimento"] = format_date((string) $payload["nascimento"]);
        }

        unset($payload["csrf"]);
        unset($payload["id"]);

        if ($update) {
            $payload["updated_by"] = $this->user->uid;
        } else {
            $payload["trash"] = 0;
            $payload["created_by"] = $this->user->uid;
        }

        return $payload;
    }

    /**
     * Retorna a resposta JSON padronizada do cliente rapido.
     */
    private function quickClientJson(bool $success, string $message, array $data = []): void
    {
        header("Content-Type: application/json; charset=utf-8");

        echo json_encode([
            "success" => $success,
            "message" => $message,
            "data" => $data,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Monta o texto que sera exibido no selectpicker do atalho de cliente.
     */
    private function quickClientLabel($cliente): string
    {
        $razao = $this->quickClientValue($cliente, "razao");
        $nome = $this->quickClientValue($cliente, "nome");
        $pessoa = $this->quickClientValue($cliente, "pessoa");
        $documento = $this->quickClientValue($cliente, "documento");
        $telefone = $this->quickClientValue($cliente, "telefone");

        $principal = trim((string) ($razao ?: $nome)) ?: "Cliente";
        $doc = $this->formatDocumento($pessoa, $documento);
        $phone = $this->formatPhone($telefone);

        return $this->buildClientLabel($principal, $doc, $phone);
    }

    private function quickClientValue($cliente, string $field): mixed
    {
        if (is_array($cliente)) {
            return $cliente[$field] ?? null;
        }

        if (!is_object($cliente)) {
            return null;
        }

        return $cliente->$field ?? null;
    }

    /**
     * Formata o documento para exibicao na listagem.
     *
     * CPF recebe mascara de CPF e CNPJ recebe mascara de CNPJ; se o valor
     * nao casar com nenhum formato conhecido, o texto limpo e exibido.
     */
    private function formatDocumento(?string $pessoa, ?string $documento): string
    {
        $value = $this->normalizeImportDocumento($documento ?? "");

        if (!$value) {
            return "-";
        }

        if ($pessoa === "F" && strlen($value) === 11) {
            return cpf($value);
        }

        if (strlen($value) === 14) {
            return cnpj($value);
        }

        return $value ?: "-";
    }

    private function formatPhone(?string $telefone): string
    {
        $value = only_numbers((string) $telefone);

        if ($value === "") {
            return "-";
        }

        if (strlen($value) === 11) {
            return preg_replace("/(\d{2})(\d{5})(\d{4})/", "($1) $2-$3", $value) ?: $value;
        }

        if (strlen($value) === 10) {
            return preg_replace("/(\d{2})(\d{4})(\d{4})/", "($1) $2-$3", $value) ?: $value;
        }

        return $value;
    }

    private function buildClientLabel(string $principal, string ...$parts): string
    {
        $items = [$principal];

        foreach ($parts as $part) {
            $value = trim($part);

            if ($value === "" || $value === "-") {
                continue;
            }

            $items[] = $value;
        }

        return implode(" - ", $items);
    }

    private function shortValue(string $value, int $length = 18): string
    {
        $value = trim($value);

        if ($value === "") {
            return "-";
        }

        if (mb_strlen($value) <= $length) {
            return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
        }

        $half = max(4, (int) floor(($length - 3) / 2));
        $start = mb_substr($value, 0, $half);
        $end = mb_substr($value, -$half);

        return htmlspecialchars($start . "..." . $end, ENT_QUOTES, "UTF-8");
    }

    private function moneyBR(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    private function statusBadge(string $status): string
    {
        $enum = CartaoStatusEnum::tryFrom(trim($status));

        return $enum ? $enum->badge() : '<span class="badge filled-outlined bg-secondary">Indefinido</span>';
    }

    /**
     * Monta o badge colorido de situacao.
     *
     * O HTML final ja sai pronto para a view, com cor, borda e texto tratados.
     */
    private function badgeColor(string $label, string $color): string
    {
        $color = trim($color) ?: "#6c757d";
        $textColor = colorContrast($color);

        return '<span class="badge filled-outlined" style="background:' . htmlspecialchars($color, ENT_QUOTES, "UTF-8") . ';border-color:' . htmlspecialchars($color, ENT_QUOTES, "UTF-8") . ';color:' . htmlspecialchars($textColor, ENT_QUOTES, "UTF-8") . ';">' . htmlspecialchars($label, ENT_QUOTES, "UTF-8") . '</span>';
    }

    /**
     * Retorna a pessoa padrao do modulo.
     *
     * Se a configuracao vier invalida, o sistema cai para PF.
     */
    private function defaultPessoaCliente(): string
    {
        $value = strtoupper(trim((string) Config::get('modulos.cliente.default_pessoa', 'F')));

        return in_array($value, ['F', 'J'], true) ? $value : 'F';
    }

    /**
     * Retorna as opcoes permitidas de PF/PJ.
     *
     * Isso permite que o cadastro seja travado em PF, em PJ ou mostre ambos.
     */
    private function pessoaOpcoesCliente(): array
    {
        $values = Config::get('modulos.cliente.allowed_pessoa', ['F', 'J']);
        $values = is_array($values) ? $values : [$values];

        $values = array_values(array_unique(array_filter(array_map(
            static fn ($value) => strtoupper(trim((string) $value)),
            $values
        ), static fn ($value) => in_array($value, ['F', 'J'], true))));

        return $values ?: ['F', 'J'];
    }

    /**
     * Indica se o cadastro deve ocultar o seletor de pessoa.
     *
     * Quando ha apenas uma opcao, a view recebe um hidden e nao renderiza
     * o bloco visual do select.
     */
    private function pessoaFixaCliente(): bool
    {
        return count($this->pessoaOpcoesCliente()) === 1;
    }

    /**
     * Indica se o modulo de cliente utiliza ramo.
     *
     * A flag controla tela, permissao de menu, importacao e colunas da lista.
     */
    private function useRamoCliente(): bool
    {
        return (bool) Config::get('modulos.cliente.use_ramo', false);
    }

    /**
     * Retorna a URL da planilha modelo adequada ao modulo.
     *
     * O arquivo muda conforme o modulo use ou nao o campo de ramo.
     */
    private function importTemplateUrl(): string
    {
        return $this->useRamoCliente()
            ? asset("docs/modelos/modelo-importacao-clientes-b.xlsx")
            : asset("docs/modelos/modelo-importacao-clientes-a.xlsx");
    }
}
