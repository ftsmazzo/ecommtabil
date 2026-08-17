<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\Redirect;
use App\Core\Request;
use App\Enums\EstadoBrasileiro;
use App\Lib\Cnpj;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\EmpresaResponsavel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EmpresaController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title"       => "Empresas",
            "active_menu" => "clientes-empresas",
            "uppers"      => implode(",", Empresa::getUppers()),
            "estados_br"  => array_map(
                fn (EstadoBrasileiro $estado) => $estado->value,
                EstadoBrasileiro::cases()
            ),
        ]);
    }

    public function index(): void
    {
        $this->authorize("empresa_gerenciar");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cadastros"  => ["url" => false, "current" => false],
                "Empresas"   => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Empresas",
                "desc"  => "Cadastre e gerencie as empresas vinculadas aos clientes",
            ],
        ]);

        $empresas = Empresa::leftJoin("cliente as c", "e.id_cliente", "=", "c.id")
            ->select("e.*", "c.razao as cliente_razao", "c.nome as cliente_nome")
            ->orderBy("e.razao")
            ->get();

        foreach ($empresas as $empresa) {
            $empresa->hash = md5((string) $empresa->id);
            $empresa->cliente_print = trim((string) ($empresa->cliente_razao ?: $empresa->cliente_nome)) ?: "-";
            $empresa->documento_print = $this->formatDocumento($empresa->pessoa, $empresa->documento);
        }

        $permissao = [
            "inserir" => $this->auth->allow("empresa_inserir"),
            "editar"  => $this->auth->allow("empresa_editar"),
            "excluir" => $this->auth->allow("empresa_excluir"),
        ];

        echo $this->view->render("admin/empresa/lista", [
            "dados"     => $empresas,
            "permissao" => $permissao,
            "csrf"      => $this->csrf->generate(),
        ]);
    }

    public function new(): void
    {
        $this->authorize("empresa_inserir");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cadastros"  => ["url" => false, "current" => false],
                "Empresas"   => ["url" => $this->router->route("admin.empresa.index"), "current" => false],
                "Nova Empresa" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Nova Empresa",
                "desc"  => "Preencha os dados da empresa",
            ],
        ]);

        echo $this->view->render("admin/empresa/novo", [
            "csrf"      => $this->csrf->generate(),
            "clientes"  => $this->listaClientes(),
            "url_back"  => $this->router->route("admin.empresa.index"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("empresa_inserir");

        $data = new Data($request->all());

        if (!$data->has("razao")) {
            $this->message->warning("Informe a razão social ou nome");
            Redirect::referer();
            return;
        }

        if (!$data->has("id_cliente")) {
            $this->message->warning("Selecione o cliente");
            Redirect::referer();
            return;
        }

        $payload = $this->normalizedData($request);
        Empresa::create($payload);

        $this->message->success("Empresa cadastrada com sucesso");
        $this->router->redirect("admin.empresa.index");
    }

    public function edit(Request $request): void
    {
        $this->authorize("empresa_editar");

        $data    = new Data($request->all());
        $empresa = Empresa::find($data->id) ?: Empresa::findByMd5($data->id);

        if (!$empresa) {
            $this->message->warning("Empresa não encontrada");
            $this->router->redirect("admin.empresa.index");
        }

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard"     => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cadastros"     => ["url" => false, "current" => false],
                "Empresas"      => ["url" => $this->router->route("admin.empresa.index"), "current" => false],
                "Editar Empresa" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Editar Empresa",
                "desc"  => "Atualize os dados da empresa",
            ],
        ]);

        $responsaveis = EmpresaResponsavel::where("id_empresa", "=", $empresa->id)
            ->orderBy("nome")
            ->get();

        foreach ($responsaveis as $r) {
            $r->hash = md5((string) $r->id);
        }

        echo $this->view->render("admin/empresa/editar", [
            "csrf"                 => $this->csrf->generate(),
            "empresa"              => $empresa,
            "clientes"             => $this->listaClientes(),
            "url_back"             => $this->router->route("admin.empresa.index"),
            "responsaveis"         => $responsaveis,
            "permissao_responsavel" => [
                "gerenciar" => $this->auth->allow("empresa_responsavel_gerenciar"),
                "inserir"   => $this->auth->allow("empresa_responsavel_inserir"),
                "editar"    => $this->auth->allow("empresa_responsavel_editar"),
                "excluir"   => $this->auth->allow("empresa_responsavel_excluir"),
            ],
        ]);
    }

    public function update(Request $request): void
    {
        $this->authorize("empresa_editar");

        $data    = new Data($request->all());
        $empresa = Empresa::find($data->id) ?: Empresa::findByMd5($data->id);

        if (!$empresa) {
            $this->message->warning("Empresa não encontrada");
            Redirect::referer();
            return;
        }

        if (!$data->has("razao")) {
            $this->message->warning("Informe a razão social ou nome");
            Redirect::referer();
            return;
        }

        $payload = $this->normalizedData($request, true);
        Empresa::updateBy($empresa->id, $payload);

        $this->message->success("Empresa atualizada com sucesso");
        $this->router->redirect("admin.empresa.editar", ["id" => $empresa->hash()]);
    }

    public function delete(Request $request): void
    {
        $this->authorize("empresa_excluir");

        $data    = new Data($request->all());
        $empresa = Empresa::find($data->id) ?: Empresa::findByMd5($data->id);

        if (!$empresa) {
            $this->message->warning("Empresa não encontrada");
            Redirect::referer();
            return;
        }

        Empresa::updateBy($empresa->id, [
            "trash"      => 1,
            "deleted_by" => $this->user->uid,
            "deleted_at" => date("Y-m-d H:i:s"),
        ]);

        $this->message->success("Empresa removida com sucesso");
        Redirect::referer();
    }

    public function getCnpj(Request $request): void
    {
        $data = new Data($request->all());
        $cnpj = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim((string) ($data->cnpj ?? $data->documento ?? ""))));

        header("Content-Type: application/json; charset=utf-8");

        if (strlen($cnpj) !== 14) {
            echo json_encode(["error" => true, "message" => "Informe um CNPJ válido."], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $info = (new Cnpj())->get($cnpj);
        } catch (\Throwable $e) {
            echo json_encode(["error" => true, "message" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            "error"   => false,
            "message" => "CNPJ localizado com sucesso.",
            "data"    => [
                "pessoa"      => "J",
                "documento"   => $info["cnpj"] ?? $cnpj,
                "razao"       => $info["nome"] ?? "",
                "nome"        => $info["fantasia"] ?? ($info["nome"] ?? ""),
                "nascimento"  => $info["abertura"] ?? "",
                "contato"     => $info["responsavel"] ?? "",
                "telefone"    => $info["telefone"] ?? "",
                "email"       => $info["email"] ?? "",
                "site"        => $info["site"] ?? "",
                "cep"         => $info["cep"] ?? "",
                "endereco"    => $info["logradouro"] ?? "",
                "numero"      => $info["numero"] ?? "",
                "complemento" => $info["complemento"] ?? "",
                "bairro"      => $info["bairro"] ?? "",
                "cidade"      => $info["municipio"] ?? "",
                "estado"      => $info["uf"] ?? "",
                "pais"        => "Brasil",
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    private function normalizedData(Request $request, bool $update = false): array
    {
        $data = new Data($request->all());

        foreach (["razao", "nome", "rg_ie", "nascimento", "contato", "telefone", "whatsapp",
                  "email", "site", "cep", "endereco", "numero", "complemento",
                  "bairro", "cidade", "estado", "pais", "observacoes"] as $field) {
            $data->nullIfEmpty($field);
        }

        $payload = $data->all();
        $payload["documento"] = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim((string) ($payload["documento"] ?? "")))) ?: null;
        $payload["cep"]       = only_numbers((string) ($payload["cep"] ?? "")) ?: null;
        $payload["telefone"]  = only_numbers((string) ($payload["telefone"] ?? "")) ?: null;
        $payload["whatsapp"]  = only_numbers((string) ($payload["whatsapp"] ?? "")) ?: null;

        if (!empty($payload["nascimento"])) {
            $payload["nascimento"] = format_date((string) $payload["nascimento"]);
        }

        unset($payload["csrf"], $payload["id"]);

        if ($update) {
            $payload["updated_by"] = $this->user->uid;
        } else {
            $payload["trash"]      = 0;
            $payload["created_by"] = $this->user->uid;
        }

        return $payload;
    }

    public function importUpload(Request $request): void
    {
        $this->authorize("empresa_inserir");

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

        $dir = base_path("storage/tmp/empresas/importacoes");
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo((string) $file["name"], PATHINFO_FILENAME));
        $target = $dir . DIRECTORY_SEPARATOR . $safeName . "." . $extension;

        if (!move_uploaded_file($file["tmp_name"], $target)) {
            $this->message->flash("Não foi possível salvar a planilha enviada", "warning", "exclamation-triangle", "alert");
            Redirect::referer();
            return;
        }

        try {
            $summary = $this->processEmpresaImport($target, $allowIncomplete);
            $message = $this->buildEmpresaImportResultAlert($summary);
            $hasIssues = (int) ($summary["issues_count"] ?? 0) > 0;
            $this->message->flash(
                $message,
                $hasIssues ? "warning" : "success",
                $hasIssues ? "exclamation-triangle" : "check-circle",
                "alert"
            );
        } catch (\Throwable $e) {
            $this->message->flash("Não foi possível importar a planilha: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8"), "danger", "exclamation-circle", "alert");
        }

        $this->router->redirect("admin.empresa.index");
    }

    private function processEmpresaImport(string $path, bool $allowIncomplete = false): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $sheetInfo = $reader->listWorksheetInfo($path);
        if (empty($sheetInfo[0]['totalRows'])) {
            throw new \RuntimeException("A planilha enviada está vazia.");
        }

        $totalRows    = (int) $sheetInfo[0]['totalRows'];
        $totalColumns = (int) ($sheetInfo[0]['totalColumns'] ?? 0);
        $lastColumn   = Coordinate::stringFromColumnIndex(max(1, $totalColumns ?: 22));

        $headers = $this->readEmpresaImportHeaders($reader, $path, $lastColumn);
        $columns = $this->mapEmpresaImportColumns($headers);

        // Cache de clientes para resolver referência sem bater no banco por linha
        $clienteCache = [];
        foreach (Cliente::select("id", "razao", "nome", "documento")->get() as $c) {
            $doc = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim((string) $c->documento)));
            if ($doc !== "") {
                $clienteCache["doc:" . $doc] = (int) $c->id;
            }
            $clienteCache["id:" . (int) $c->id] = (int) $c->id;
            $key = $this->normalizeEmpresaImportKey((string) ($c->razao ?: $c->nome));
            if ($key !== "") {
                $clienteCache["nome:" . $key] = (int) $c->id;
            }
        }

        // Cache de documentos já existentes para evitar duplicidade
        $existingDocuments = [];
        foreach (Empresa::select("documento")->get() as $e) {
            $doc = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim((string) $e->documento)));
            if ($doc !== "") {
                $existingDocuments[$doc] = true;
            }
        }

        $imported      = 0;
        $skipped       = 0;
        $issues        = [];
        $seenDocuments = [];
        $chunkSize     = 300;

        $insertColumns = [
            "id_cliente",
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
            $endRow    = min($totalRows, $startRow + $chunkSize - 1);
            $chunkRows = $this->readEmpresaImportChunk($reader, $path, $startRow, $endRow, $lastColumn);
            $chunkInsertRows = [];

            foreach ($chunkRows as $rowNumber => $row) {
                $line           = (int) $rowNumber;
                $rowDataResult  = $this->buildEmpresaImportRow($row, $columns, $clienteCache, $line, $allowIncomplete);

                if (!empty($rowDataResult['error'])) {
                    $skipped++;
                    $issues[] = ["line" => $line, "type" => "error", "message" => $rowDataResult['error']];
                    continue;
                }

                $rowData = $rowDataResult['data'];

                if (!empty($rowData['documento'])) {
                    if (isset($existingDocuments[$rowData['documento']]) || isset($seenDocuments[$rowData['documento']])) {
                        $skipped++;
                        $issues[] = ["line" => $line, "type" => "duplicate", "message" => "Empresa duplicada. Documento {$rowData['documento']} já existe."];
                        continue;
                    }
                    $seenDocuments[$rowData['documento']] = true;
                }

                $rowData = $this->normalizeEmpresaImportInsertRow($rowData);
                $rowData['created_by'] = $this->user->uid;
                $rowData['trash']      = 0;

                $chunkInsertRows[] = $rowData;
            }

            if ($chunkInsertRows) {
                Empresa::insertMany($insertColumns, $chunkInsertRows, 300);
                $imported += count($chunkInsertRows);
            }
        }

        return [
            "imported"     => $imported,
            "skipped"      => $skipped,
            "issues"       => $issues,
            "issues_count" => count($issues),
        ];
    }

    private function readEmpresaImportHeaders($reader, string $path, string $lastColumn): array
    {
        $filter = new class(1, 1, Coordinate::columnIndexFromString($lastColumn)) implements IReadFilter {
            private int $startRow;
            private int $endRow;
            private int $maxColumn;

            public function __construct(int $startRow, int $chunkSize, int $maxColumn)
            {
                $this->startRow  = $startRow;
                $this->endRow    = $startRow + $chunkSize - 1;
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
        $sheet       = $spreadsheet->getActiveSheet();
        $headers     = $sheet->rangeToArray('A1:' . $lastColumn . '1', null, true, false, true);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $headers[1] ?? [];
    }

    private function readEmpresaImportChunk($reader, string $path, int $startRow, int $endRow, string $lastColumn): array
    {
        $filter = new class($startRow, $endRow, Coordinate::columnIndexFromString($lastColumn)) implements IReadFilter {
            private int $startRow;
            private int $endRow;
            private int $maxColumn;

            public function __construct(int $startRow, int $endRow, int $maxColumn)
            {
                $this->startRow  = $startRow;
                $this->endRow    = $endRow;
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
        $sheet       = $spreadsheet->getActiveSheet();
        $range       = 'A' . $startRow . ':' . $lastColumn . $endRow;
        $rows        = $sheet->rangeToArray($range, null, true, false, true);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    private function mapEmpresaImportColumns(array $headers): array
    {
        $mapped     = [];
        $normalized = [];

        foreach ($headers as $column => $label) {
            $normalized[$this->normalizeEmpresaImportKey((string) $label)] = $column;
        }

        $aliases = [
            "cliente"      => ["CLIENTE", "ID CLIENTE", "CNPJ CLIENTE", "RAZAO CLIENTE", "EMPRESA MÃE"],
            "pessoa"       => ["TIPO DE PESSOA", "PESSOA", "TIPO PESSOA"],
            "documento"    => ["CPF CNPJ", "CPF OU CNPJ", "CPF", "CNPJ", "DOCUMENTO"],
            "razao"        => ["RAZAO SOCIAL / NOME COMPLETO", "RAZAO SOCIAL NOME COMPLETO", "RAZAO SOCIAL", "NOME COMPLETO", "RAZAO", "NOME"],
            "nome"         => ["NOME FANTASIA / APELIDO", "NOME FANTASIA APELIDO", "NOME FANTASIA", "APELIDO"],
            "rg_ie"        => ["IE / RG", "IE RG", "RG IE", "RG", "IE"],
            "nascimento"   => ["DATA DE ABERTURA / NASCIMENTO", "DATA DE ABERTURA NASCIMENTO", "DATA DE ABERTURA", "NASCIMENTO", "ABERTURA"],
            "contato"      => ["CONTATO"],
            "telefone"     => ["TELEFONE", "CELULAR"],
            "whatsapp"     => ["WHATSAPP"],
            "email"        => ["E-MAIL", "E MAIL", "EMAIL"],
            "site"         => ["SITE", "WEBSITE"],
            "cep"          => ["CEP"],
            "endereco"     => ["LOGRADOURO", "ENDERECO"],
            "numero"       => ["NUMERO", "N"],
            "complemento"  => ["COMPLEMENTO"],
            "bairro"       => ["BAIRRO"],
            "cidade"       => ["CIDADE"],
            "estado"       => ["ESTADO", "UF"],
            "pais"         => ["PAIS"],
            "observacoes"  => ["OBSERVACOES"],
        ];

        foreach ($aliases as $key => $possibleHeaders) {
            foreach ($possibleHeaders as $alias) {
                $aliasKey = $this->normalizeEmpresaImportKey($alias);
                if (isset($normalized[$aliasKey])) {
                    $mapped[$key] = $normalized[$aliasKey];
                    break;
                }
            }
        }

        // Fallback por posição — espelha o modelo padrão
        $fallbacks = [
            "cliente"     => "A",
            "pessoa"      => "B",
            "documento"   => "C",
            "razao"       => "D",
            "nome"        => "E",
            "rg_ie"       => "F",
            "nascimento"  => "G",
            "contato"     => "H",
            "telefone"    => "I",
            "whatsapp"    => "J",
            "email"       => "K",
            "site"        => "L",
            "cep"         => "M",
            "endereco"    => "N",
            "numero"      => "O",
            "complemento" => "P",
            "bairro"      => "Q",
            "cidade"      => "R",
            "estado"      => "S",
            "pais"        => "T",
            "observacoes" => "U",
        ];

        foreach ($fallbacks as $key => $column) {
            if (!isset($mapped[$key]) && isset($headers[$column])) {
                $mapped[$key] = $column;
            }
        }

        return $mapped;
    }

    private function buildEmpresaImportRow(array $row, array $columns, array $clienteCache, int $line, bool $allowIncomplete): array
    {
        $cell = fn (string $key) => isset($columns[$key]) ? ($row[$columns[$key]] ?? null) : null;

        $razaoValue    = $cell('razao');
        $nomeValue     = $cell('nome');
        $documentoRaw  = $cell('documento');
        $pessoaRaw     = $cell('pessoa');
        $clienteRaw    = $cell('cliente');

        $documento = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim((string) $documentoRaw)));
        $razao     = $this->normalizeEmpresaImportFieldValue('razao', $razaoValue);
        $nome      = $this->normalizeEmpresaImportFieldValue('nome', $nomeValue);

        if (!$allowIncomplete && $documento === "" && $razao === "" && $nome === "") {
            return ["error" => "Linha {$line}: linha vazia ou sem dados mínimos."];
        }

        // Pessoa: inferida pelo documento se não informada
        $pessoa = $this->parseEmpresaImportPessoa($pessoaRaw, $documento);

        if ($documento !== "" && $pessoa === "J" && strlen($documento) !== 14) {
            return ["error" => "Linha {$line}: CNPJ deve ter 14 dígitos."];
        }
        if ($documento !== "" && $pessoa === "F" && strlen($documento) !== 11) {
            return ["error" => "Linha {$line}: CPF deve ter 11 dígitos."];
        }

        $cnpjValidator = function_exists("validaCNPJBR") ? "validaCNPJBR" : "validaCNPJ";
        if ($documento !== "" && $pessoa === "J" && function_exists($cnpjValidator) && !$cnpjValidator($documento)) {
            return ["error" => "Linha {$line}: CNPJ inválido."];
        }
        if ($documento !== "" && $pessoa === "F" && function_exists("validaCPF") && !validaCPF($documento)) {
            return ["error" => "Linha {$line}: CPF inválido."];
        }

        $idCliente = $this->resolveEmpresaImportClienteId($clienteRaw, $clienteCache);

        $razao = $razao ?: $nome ?: ($allowIncomplete ? "" : $documento);
        $nome  = $nome ?: $razao;

        return [
            "data" => [
                "id_cliente"  => $idCliente,
                "pessoa"      => $pessoa,
                "documento"   => $documento ?: ($allowIncomplete ? "" : null),
                "rg_ie"       => $this->normalizeEmpresaImportFieldValue('rg_ie', $cell('rg_ie')),
                "razao"       => $razao ?: ($allowIncomplete ? "" : null),
                "nome"        => $nome ?: ($allowIncomplete ? "" : null),
                "nascimento"  => $this->parseEmpresaImportDate($cell('nascimento')),
                "contato"     => $this->normalizeEmpresaImportFieldValue('contato', $cell('contato')),
                "telefone"    => only_numbers((string) $cell('telefone')) ?: null,
                "whatsapp"    => only_numbers((string) $cell('whatsapp')) ?: null,
                "email"       => strtolower(trim((string) $cell('email'))) ?: null,
                "site"        => trim((string) $cell('site')) ?: null,
                "cep"         => only_numbers((string) $cell('cep')) ?: null,
                "endereco"    => $this->normalizeEmpresaImportFieldValue('endereco', $cell('endereco')),
                "numero"      => $this->normalizeEmpresaImportFieldValue('numero', $cell('numero')),
                "complemento" => $this->normalizeEmpresaImportFieldValue('complemento', $cell('complemento')),
                "bairro"      => $this->normalizeEmpresaImportFieldValue('bairro', $cell('bairro')),
                "cidade"      => $this->normalizeEmpresaImportFieldValue('cidade', $cell('cidade')),
                "estado"      => $this->normalizeEmpresaImportEstado($cell('estado')),
                "pais"        => $this->normalizeEmpresaImportFieldValue('pais', $cell('pais')) ?: "Brasil",
                "observacoes" => trim((string) $cell('observacoes')) ?: null,
            ],
        ];
    }

    private function resolveEmpresaImportClienteId(mixed $value, array $clienteCache): ?int
    {
        $raw = trim((string) $value);
        if ($raw === "") {
            return null;
        }

        // Tenta como ID numérico
        if (ctype_digit($raw) && isset($clienteCache["id:" . (int) $raw])) {
            return $clienteCache["id:" . (int) $raw];
        }

        // Tenta como CNPJ/CPF
        $doc = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($raw));
        if ($doc !== "" && isset($clienteCache["doc:" . $doc])) {
            return $clienteCache["doc:" . $doc];
        }

        // Tenta por razão social (fuzzy)
        $key = $this->normalizeEmpresaImportKey($raw);
        if ($key !== "" && isset($clienteCache["nome:" . $key])) {
            return $clienteCache["nome:" . $key];
        }

        // Fuzzy com similar_text
        $bestId    = null;
        $bestScore = 0;
        foreach ($clienteCache as $cacheKey => $id) {
            if (!str_starts_with($cacheKey, "nome:")) {
                continue;
            }
            similar_text($key, substr($cacheKey, 5), $score);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId    = $id;
            }
        }

        return ($bestScore >= 85 && $bestId) ? $bestId : null;
    }

    private function parseEmpresaImportPessoa(mixed $value, string $documento): string
    {
        $label   = $this->normalizeEmpresaImportKey((string) $value);
        $digits  = only_numbers($documento);
        $juridica = ["J", "PJ", "JURIDICA", "PESSOA JURIDICA", "JURIDICO", "EMPRESA"];
        $fisica   = ["F", "PF", "FISICA", "PESSOA FISICA"];

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

        return strlen($digits) === 14 ? "J" : "F";
    }

    private function parseEmpresaImportDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format("Y-m-d");
        }

        if (is_numeric($value) && (float) $value > 0) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format("Y-m-d");
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

    private function normalizeEmpresaImportFieldValue(string $field, mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === "") {
            return "";
        }

        if (in_array($field, Empresa::getUppers(), true)) {
            return function_exists('mb_strtoupper') ? mb_strtoupper($text, 'UTF-8') : strtoupper($text);
        }

        return $text;
    }

    private function normalizeEmpresaImportInsertRow(array $row): array
    {
        foreach (Empresa::getUppers() as $field) {
            if (!array_key_exists($field, $row) || $row[$field] === null || $row[$field] === '') {
                continue;
            }
            $row[$field] = function_exists('mb_strtoupper') ? mb_strtoupper((string) $row[$field], 'UTF-8') : strtoupper((string) $row[$field]);
        }

        $row['estado'] = $this->normalizeEmpresaImportEstado($row['estado'] ?? null);

        return $row;
    }

    private function normalizeEmpresaImportEstado(mixed $value): string
    {
        $text = $this->normalizeEmpresaImportKey((string) $value);
        if ($text === '') {
            return '';
        }

        $ufMap = [
            'ACRE' => 'AC', 'ALAGOAS' => 'AL', 'AMAPA' => 'AP', 'AMAZONAS' => 'AM',
            'BAHIA' => 'BA', 'CEARA' => 'CE', 'DISTRITO FEDERAL' => 'DF',
            'ESPIRITO SANTO' => 'ES', 'GOIAS' => 'GO', 'MARANHAO' => 'MA',
            'MATO GROSSO' => 'MT', 'MATO GROSSO DO SUL' => 'MS', 'MINAS GERAIS' => 'MG',
            'PARA' => 'PA', 'PARAIBA' => 'PB', 'PARANA' => 'PR', 'PERNAMBUCO' => 'PE',
            'PIAUI' => 'PI', 'RIO DE JANEIRO' => 'RJ', 'RIO GRANDE DO NORTE' => 'RN',
            'RIO GRANDE DO SUL' => 'RS', 'RONDONIA' => 'RO', 'RORAIMA' => 'RR',
            'SANTA CATARINA' => 'SC', 'SAO PAULO' => 'SP', 'SERGIPE' => 'SE',
            'TOCANTINS' => 'TO',
        ];

        if (isset($ufMap[$text])) {
            return $ufMap[$text];
        }

        $estados = array_map(fn (EstadoBrasileiro $e) => $e->value, EstadoBrasileiro::cases());
        return in_array($text, $estados, true) ? $text : '';
    }

    private function normalizeEmpresaImportKey(string $value): string
    {
        $value = trim($value);
        if ($value === "") {
            return "";
        }

        $value = preg_replace('/\s+/u', ' ', $value);
        $value = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^A-Za-z0-9]+/', ' ', $value);
        $value = trim(preg_replace('/\s+/u', ' ', $value));

        return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
    }

    private function buildEmpresaImportResultAlert(array $summary): string
    {
        $imported = (int) ($summary["imported"] ?? 0);
        $skipped  = (int) ($summary["skipped"] ?? 0);
        $issues   = is_array($summary["issues"] ?? null) ? $summary["issues"] : [];

        $html   = [];
        $html[] = '<div class="mb-1"><strong>Importação concluída.</strong></div>';
        $html[] = '<div class="mb-1">' . $imported . ' empresa(s) importada(s).</div>';

        if ($skipped > 0) {
            $html[] = '<div class="mb-1">' . $skipped . ' linha(s) ignorada(s).</div>';
        }

        if (!empty($issues)) {
            $html[] = '<div class="mb-1"><strong>Ocorrências:</strong></div>';
            $html[] = '<ul class="mb-0 ps-3">';
            $limit  = 20;

            foreach (array_slice($issues, 0, $limit) as $issue) {
                $lineNum = (int) ($issue["line"] ?? 0);
                $msg     = htmlspecialchars(preg_replace('/^Linha\s+\d+\s*:\s*/i', '', (string) ($issue["message"] ?? "")) ?: (string) ($issue["message"] ?? "Erro."), ENT_QUOTES, 'UTF-8');
                $html[]  = '<li>Linha ' . $lineNum . ': ' . $msg . '</li>';
            }

            if (count($issues) > $limit) {
                $html[] = '<li>... e mais ' . (count($issues) - $limit) . ' ocorrência(s).</li>';
            }

            $html[] = '</ul>';
        }

        return implode('', $html);
    }

    private function listaClientes(): array
    {
        return Cliente::orderBy("razao")->get();
    }

    private function formatDocumento(?string $pessoa, ?string $documento): string
    {
        $value = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim((string) $documento)));

        if (!$value) {
            return "-";
        }

        if ($pessoa === "F" && strlen($value) === 11) {
            return function_exists("cpf") ? cpf($value) : $value;
        }

        if (strlen($value) === 14) {
            return function_exists("cnpj") ? cnpj($value) : $value;
        }

        return $value;
    }
}
