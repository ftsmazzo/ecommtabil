<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\DB;
use App\Core\Redirect;
use App\Core\Request;
use App\Models\DreConta;
use App\Models\TipoDemonstrativo;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DreContaController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title"       => "Plano de Contas",
            "active_menu" => "configuracoes-plano-contas",
            "page"        => [
                "title" => "Plano de Contas",
                "desc"  => "Estrutura hierárquica do demonstrativo financeiro",
            ],
        ]);
    }

    public function index(Request $request): void
    {
        $this->authorize("plano_contas_gerenciar");

        $data = new Data($request->all());
        $tipo = TipoDemonstrativo::existeSigla($data->tipo ?? "")
            ? $data->tipo
            : TipoDemonstrativo::padrao()?->sigla;

        $this->view->addData([
            "breadcrumb" => [
                "Configurações"   => ["url" => false, "current" => false],
                "Plano de Contas" => ["url" => false, "current" => true],
            ],
        ]);

        echo $this->view->render("admin/dre_conta/index", [
            "sinteticas" => DreConta::porTipoConta($tipo, "sintetica"),
            "analiticas" => DreConta::porTipoConta($tipo, "analitica"),
            "tipo"       => $tipo,
            "tipos"      => TipoDemonstrativo::options(),
            "permissao"  => [
                "inserir" => $this->auth->allow("plano_contas_inserir"),
                "editar"  => $this->auth->allow("plano_contas_editar"),
                "excluir" => $this->auth->allow("plano_contas_excluir"),
            ],
            "csrf" => $this->csrf->generate(),
        ]);
    }

    public function new(Request $request): void
    {
        $this->authorize("plano_contas_inserir");

        $data     = new Data($request->all());
        $idPai    = (int) ($data->id_pai ?? 0) ?: null;
        $pai      = $idPai ? DreConta::find($idPai) : null;
        $tipo     = $pai ? $pai->tipo_demonstrativo
            : (TipoDemonstrativo::existeSigla($data->tipo_demonstrativo ?? "")
                ? $data->tipo_demonstrativo
                : (TipoDemonstrativo::existeSigla($data->tipo ?? "") ? $data->tipo : TipoDemonstrativo::padrao()?->sigla));
        $tipoConta = $pai ? $pai->tipo
            : (in_array($data->tipo_conta ?? "", ["sintetica", "analitica"]) ? $data->tipo_conta : "analitica");

        $nivel = $pai ? min((int) $pai->nivel + 1, 3) : 1;

        echo $this->view->render("admin/dre_conta/form", [
            "csrf"       => $this->csrf->generate(),
            "conta"      => null,
            "pai"        => $pai,
            "nivel"      => $nivel,
            "tipo"       => $tipo,
            "tipoConta"  => $tipoConta,
            "tipos"      => TipoDemonstrativo::options(),
            "url_action" => $this->router->route("admin.dre.conta.insert"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("plano_contas_inserir");

        $data  = new Data($request->all());
        $idPai = (int) ($data->id_pai ?? 0) ?: null;

        if (!$data->has("nome")) {
            $this->message->warning("Informe o nome da conta");
            Redirect::referer();
            return;
        }

        $tipoDemo = TipoDemonstrativo::existeSigla($data->tipo_demonstrativo ?? "")
            ? $data->tipo_demonstrativo
            : TipoDemonstrativo::padrao()?->sigla;
        $nivel    = 1;
        if ($idPai) {
            $pai      = DreConta::find($idPai);
            $nivel    = $pai ? min((int) $pai->nivel + 1, 3) : 1;
            $tipoDemo = $pai ? $pai->tipo_demonstrativo : $tipoDemo;
        }

        $ordem = DB::table("dre_conta");
        $ordem = $idPai ? $ordem->where("id_pai", "=", $idPai) : $ordem->whereNull("id_pai");
        $ordem = $ordem
            ->where("tipo_demonstrativo", "=", $tipoDemo)
            ->where("trash", "=", 0)
            ->count();

        DreConta::create([
            "tipo_demonstrativo" => $tipoDemo,
            "id_pai"       => $idPai,
            "nivel"        => $nivel,
            "codigo"       => DreConta::gerarCodigo($idPai, $tipoDemo),
            "nome"         => trim((string) $data->nome),
            "tipo"         => in_array($data->tipo, ["sintetica", "analitica"]) ? $data->tipo : "analitica",
            "natureza"     => in_array($data->natureza, ["aumenta", "diminui"]) ? $data->natureza : "aumenta",
            "sinal"        => ($data->natureza ?? "") === "diminui" ? -1 : 1,
            "ordem"        => $ordem,
            "trash"        => 0,
            "created_by"   => $this->user->uid,
        ]);

        $this->message->success("Conta cadastrada com sucesso");
        $this->router->redirect("admin.dre.conta.index", ["tipo" => $tipoDemo]);
    }

    public function edit(Request $request): void
    {
        $this->authorize("plano_contas_editar");

        $data  = new Data($request->all());
        $conta = DreConta::find($data->id);

        if (!$conta) {
            $this->message->warning("Conta não encontrada");
            $this->router->redirect("admin.dre.conta.index");
            return;
        }

        $pai = $conta->id_pai ? DreConta::find($conta->id_pai) : null;

        echo $this->view->render("admin/dre_conta/form", [
            "csrf"       => $this->csrf->generate(),
            "conta"      => $conta,
            "pai"        => $pai,
            "nivel"      => (int) $conta->nivel,
            "tipo"       => $conta->tipo_demonstrativo,
            "tipoConta"  => $conta->tipo,
            "tipos"      => TipoDemonstrativo::options(),
            "url_action" => $this->router->route("admin.dre.conta.update"),
        ]);
    }

    public function update(Request $request): void
    {
        $this->authorize("plano_contas_editar");

        $data  = new Data($request->all());
        $conta = DreConta::find($data->id);

        if (!$conta) {
            $this->message->warning("Conta não encontrada");
            Redirect::referer();
            return;
        }

        if (!$data->has("nome")) {
            $this->message->warning("Informe o nome da conta");
            Redirect::referer();
            return;
        }

        DreConta::updateBy($conta->id, [
            "nome"       => trim((string) $data->nome),
            "tipo"       => in_array($data->tipo, ["sintetica", "analitica"]) ? $data->tipo : $conta->tipo,
            "natureza"   => in_array($data->natureza, ["aumenta", "diminui"]) ? $data->natureza : $conta->natureza,
            "sinal"      => ($data->natureza ?? $conta->natureza) === "diminui" ? -1 : 1,
            "updated_by" => $this->user->uid,
        ]);

        $this->message->success("Conta atualizada com sucesso");
        $this->router->redirect("admin.dre.conta.index", ["tipo" => $conta->tipo_demonstrativo]);
    }

    public function delete(Request $request): void
    {
        $this->authorize("plano_contas_excluir");

        $data  = new Data($request->all());
        $conta = DreConta::find($data->id);

        if (!$conta) {
            $this->message->warning("Conta não encontrada");
            Redirect::referer();
            return;
        }

        // Não permite excluir se tiver filhos ativos
        $filhos = DB::table("dre_conta")
            ->where("id_pai", "=", $conta->id)
            ->where("trash", "=", 0)
            ->count();

        if ($filhos > 0) {
            $this->message->warning("Remova as contas filhas antes de excluir este item");
            Redirect::referer();
            return;
        }

        DreConta::updateBy($conta->id, [
            "trash"      => 1,
            "updated_by" => $this->user->uid,
        ]);

        $this->message->success("Conta removida com sucesso");
        Redirect::referer();
    }

    public function downloadModelo(Request $request): void
    {
        $this->authorize("plano_contas_gerenciar");

        $data = new Data($request->all());
        $tipo = TipoDemonstrativo::existeSigla($data->tipo ?? "")
            ? $data->tipo
            : TipoDemonstrativo::padrao()?->sigla;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Plano de Contas");

        $headers = ["Descrição", "Tipo (A / S)", "Natureza (A / D)"];
        $headerHelp = [
            "Nome da conta",
            "A = Analítica, S = Sintética",
            "A = Aumenta (receitas), D = Diminui (despesas)",
        ];

        $colLetters = ["A", "B", "C"];
        foreach ($headers as $i => $label) {
            $col = $colLetters[$i];
            $cell = $sheet->getCell($col . "1");
            $cell->setValue($label);
            $sheet->getComment($col . "1")->getText()->createTextRun($headerHelp[$i]);
            $style = $sheet->getStyle($col . "1");
            $style->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color("FFFFFF"));
            $style->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color("1e3a5f"));
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getColumnDimension($col)->setWidth(36);
        }

        $sheet->freezePane("A2");

        $writer = new Xlsx($spreadsheet);
        $filename = "modelo_plano_contas_{$tipo}_" . date("Ymd") . ".xlsx";

        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header("Cache-Control: max-age=0");
        $writer->save("php://output");
        exit;
    }

    public function importar(Request $request): void
    {
        $this->authorize("plano_contas_gerenciar");

        $data = new Data($request->all());
        $tipo = TipoDemonstrativo::existeSigla($data->tipo ?? "")
            ? $data->tipo
            : TipoDemonstrativo::padrao()?->sigla;

        $file = $request->file("arquivo");
        if (!$file || $file["error"] !== UPLOAD_ERR_OK) {
            $this->message->warning("Selecione um arquivo para importar");
            Redirect::referer();
            return;
        }

        $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        if (!in_array($ext, ["xlsx", "xls", "csv"])) {
            $this->message->warning("Formato inválido. Use XLSX, XLS ou CSV.");
            Redirect::referer();
            return;
        }

        try {
            $reader = match ($ext) {
                "xlsx" => new \PhpOffice\PhpSpreadsheet\Reader\Xlsx(),
                "xls"  => new \PhpOffice\PhpSpreadsheet\Reader\Xls(),
                "csv"  => new \PhpOffice\PhpSpreadsheet\Reader\Csv(),
            };
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file["tmp_name"]);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
        } catch (\Throwable $e) {
            $this->message->error("Erro ao ler o arquivo: " . $e->getMessage());
            Redirect::referer();
            return;
        }

        // Remove header row
        array_shift($rows);
        $rows = array_values(array_filter($rows, fn($r) => !empty(trim((string) ($r["A"] ?? "")))));

        if (empty($rows)) {
            $this->message->warning("Nenhuma linha com dados encontrada.");
            Redirect::referer();
            return;
        }

        $importados = 0;
        $pulos = 0;

        $ordem = DB::table("dre_conta")
            ->where("tipo_demonstrativo", "=", $tipo)
            ->where("trash", "=", 0)
            ->count();
        $ultimoPaiId = null;

        foreach ($rows as $i => $row) {
            $linha = $i + 2;
            $descricao = trim((string) ($row["A"] ?? ""));
            $tipoContaRaw = strtolower(trim((string) ($row["B"] ?? "")));
            $naturezaRaw = strtolower(trim((string) ($row["C"] ?? "")));

            if ($descricao === "") {
                continue;
            }

            $tipoConta = str_starts_with($tipoContaRaw, "a") ? "analitica"
                : (str_starts_with($tipoContaRaw, "s") ? "sintetica" : null);

            if (!$tipoConta) {
                continue;
            }

            $natureza = str_starts_with($naturezaRaw, "a") ? "aumenta"
                : (str_starts_with($naturezaRaw, "d") ? "diminui" : null);

            if (!$natureza) {
                continue;
            }

            $existente = DB::table("dre_conta")
                ->where("tipo_demonstrativo", "=", $tipo)
                ->where("nome", "=", $descricao)
                ->where("tipo", "=", $tipoConta)
                ->where("natureza", "=", $natureza)
                ->where("trash", "=", 0)
                ->first();

            if ($existente) {
                $pulos++;
                if ($tipoConta === "sintetica") {
                    $ultimoPaiId = (int) $existente->id;
                }
                continue;
            }

            $idPai = null;
            $nivel = 1;
            $codigo = DreConta::gerarCodigo(null, $tipo);

            if ($tipoConta === "analitica" && $ultimoPaiId) {
                $idPai = $ultimoPaiId;
                $nivel = 2;
                $codigo = DreConta::gerarCodigo($ultimoPaiId, $tipo);
            }

            DreConta::create([
                "tipo_demonstrativo" => $tipo,
                "id_pai"       => $idPai,
                "nivel"        => $nivel,
                "codigo"       => $codigo,
                "nome"         => $descricao,
                "tipo"         => $tipoConta,
                "natureza"     => $natureza,
                "sinal"        => $natureza === "diminui" ? -1 : 1,
                "ordem"        => $ordem++,
                "trash"        => 0,
                "created_by"   => $this->user->uid,
            ]);

            if ($tipoConta === "sintetica") {
                $ultimoPaiId = DB::table("dre_conta")->last("id")->id ?? null;
            }

            $importados++;
        }

        $msg = "{$importados} conta(s) importada(s) com sucesso.";
        if ($pulos) {
            $msg .= " {$pulos} conta(s) já existente(s) e foram mantidas.";
        }

        $this->router->redirect("admin.dre.conta.index", ["tipo" => $tipo]);
    }

    /**
     * Endpoint AJAX para reordenar via drag-and-drop.
     * Recebe: ids[] — array de ids na nova ordem
     */
    public function reorder(Request $request): void
    {
        $this->authorize("plano_contas_editar");

        header("Content-Type: application/json; charset=utf-8");

        $data = new Data($request->all());
        $ids  = array_filter(array_map("intval", (array) ($data->ids ?? [])));

        if (empty($ids)) {
            echo json_encode(["error" => true, "message" => "Nenhum id recebido"]);
            return;
        }

        DreConta::reordenar($ids);

        echo json_encode(["error" => false]);
    }
}
