<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\DB;
use App\Models\IndiceFinanceiro;
use App\Models\IndiceFinanceiroHistorico;

class IndiceFinanceiroController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title"       => "Índices Financeiros",
            "active_menu" => "cadastros-indice-financeiro",
            "page" => [
                "title" => "Índices Financeiros",
                "desc"  => "Cadastre e acompanhe os índices financeiros (SELIC, IPCA, IGPM, Dólar...)",
            ],
        ]);
    }

    public function index(): void
    {
        $this->authorize("indice_financeiro_gerenciar");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard"          => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cadastros"          => ["url" => false, "current" => false],
                "Índices Financeiros" => ["url" => false, "current" => true],
            ],
        ]);

        $dados = IndiceFinanceiro::orderBy("nome")->get();

        $latestRows = DB::run("
            SELECT h1.id_indice, h1.valor, h1.data_referencia
            FROM indice_financeiro_historico h1
            INNER JOIN (
                SELECT id_indice, MAX(data_referencia) AS max_data
                FROM indice_financeiro_historico
                GROUP BY id_indice
            ) latest ON h1.id_indice = latest.id_indice AND h1.data_referencia = latest.max_data
        ");

        $latestMap = [];
        foreach ($latestRows as $row) {
            $latestMap[(int) $row["id_indice"]] = $row;
        }

        foreach ($dados as $d) {
            $d->hash        = md5((string) $d->id);
            $d->ultimo_valor = $latestMap[$d->id]["valor"] ?? null;
            $d->ultima_data  = $latestMap[$d->id]["data_referencia"] ?? null;
        }

        $permissao = [
            "inserir" => $this->auth->allow("indice_financeiro_inserir"),
            "editar"  => $this->auth->allow("indice_financeiro_editar"),
            "excluir" => $this->auth->allow("indice_financeiro_excluir"),
        ];

        echo $this->view->render("admin/indice_financeiro/index", [
            "dados"     => $dados,
            "permissao" => $permissao,
        ]);
    }

    public function new(): void
    {
        $this->authorize("indice_financeiro_inserir");

        echo $this->view->render("admin/indice_financeiro/form", [
            "csrf"       => $this->csrf->generate(),
            "indice"     => false,
            "url_action" => $this->router->route("admin.indice.financeiro.insert"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("indice_financeiro_inserir");

        $data = new Data($request->all());

        if (!$data->has("nome") || !$data->has("sigla")) {
            $this->message->warning("Informe o nome e a sigla do índice");
            Redirect::referer();
            return;
        }

        $payload = $data->all();
        unset($payload["csrf"], $payload["id"]);
        $payload["created_by"] = $this->user->uid;

        if (empty($payload["descricao"])) {
            $payload["descricao"] = null;
        }
        if (empty($payload["unidade"])) {
            $payload["unidade"] = null;
        }

        IndiceFinanceiro::create($payload);

        $this->message->success("Índice financeiro cadastrado com sucesso");
        $this->router->redirect("admin.indice.financeiro.index");
    }

    public function edit(Request $request): void
    {
        $this->authorize("indice_financeiro_editar");

        $data   = new Data($request->all());
        $indice = IndiceFinanceiro::findByMd5($data->id) ?: IndiceFinanceiro::find($data->id);

        if (!$indice) {
            $this->message->warning("Índice financeiro não encontrado");
            $this->router->redirect("admin.indice.financeiro.index");
            return;
        }

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard"          => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cadastros"          => ["url" => false, "current" => false],
                "Índices Financeiros" => ["url" => $this->router->route("admin.indice.financeiro.index"), "current" => false],
                $indice->sigla        => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => $indice->sigla . " — " . $indice->nome,
                "desc"  => "Edite os dados do índice e gerencie o histórico de valores",
            ],
        ]);

        $historico = IndiceFinanceiroHistorico::where("id_indice", "=", $indice->id)
            ->orderBy("data_referencia", "DESC")
            ->get();

        foreach ($historico as $h) {
            $h->hash = md5((string) $h->id);
        }

        $permissao = [
            "inserir" => $this->auth->allow("indice_financeiro_inserir"),
            "editar"  => $this->auth->allow("indice_financeiro_editar"),
            "excluir" => $this->auth->allow("indice_financeiro_excluir"),
        ];

        echo $this->view->render("admin/indice_financeiro/editar", [
            "csrf"       => $this->csrf->generate(),
            "indice"     => $indice,
            "historico"  => $historico,
            "permissao"  => $permissao,
            "url_action" => $this->router->route("admin.indice.financeiro.update"),
            "url_back"   => $this->router->route("admin.indice.financeiro.index"),
        ]);
    }

    public function update(Request $request): void
    {
        $this->authorize("indice_financeiro_editar");

        $data   = new Data($request->all());
        $indice = IndiceFinanceiro::findByMd5($data->id) ?: IndiceFinanceiro::find($data->id);

        if (!$indice) {
            $this->message->warning("Índice financeiro não encontrado");
            Redirect::referer();
            return;
        }

        if (!$data->has("nome") || !$data->has("sigla")) {
            $this->message->warning("Informe o nome e a sigla do índice");
            Redirect::referer();
            return;
        }

        $payload = $data->all();
        unset($payload["csrf"], $payload["id"]);
        $payload["updated_by"] = $this->user->uid;

        if (empty($payload["descricao"])) {
            $payload["descricao"] = null;
        }
        if (empty($payload["unidade"])) {
            $payload["unidade"] = null;
        }

        IndiceFinanceiro::updateBy($indice->id, $payload);

        $this->message->success("Índice financeiro atualizado com sucesso");
        $this->router->redirect("admin.indice.financeiro.editar", ["id" => md5((string) $indice->id)]);
    }

    public function delete(Request $request): void
    {
        $this->authorize("indice_financeiro_excluir");

        $data   = new Data($request->all());
        $indice = IndiceFinanceiro::findByMd5($data->id) ?: IndiceFinanceiro::find($data->id);

        if (!$indice) {
            $this->message->warning("Índice financeiro não encontrado");
            Redirect::referer();
            return;
        }

        IndiceFinanceiro::deleteById($indice->id);

        $this->message->success("Índice financeiro removido com sucesso");
        Redirect::referer();
    }

    /* ------------------------------------------------------------------ */
    /* HISTÓRICO                                                           */
    /* ------------------------------------------------------------------ */

    public function historicoNew(Request $request): void
    {
        $this->authorize("indice_financeiro_inserir");

        $data   = new Data($request->all());
        $indice = IndiceFinanceiro::findByMd5($data->id_indice) ?: IndiceFinanceiro::find($data->id_indice);

        if (!$indice) {
            echo json_encode(["error" => true, "message" => "Índice não encontrado."], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo $this->view->render("admin/indice_financeiro/historico_form", [
            "csrf"       => $this->csrf->generate(),
            "historico"  => false,
            "indice"     => $indice,
            "url_action" => $this->router->route("admin.indice.financeiro.historico.insert"),
        ]);
    }

    public function historicoCreate(Request $request): void
    {
        $this->authorize("indice_financeiro_inserir");

        $data   = new Data($request->all());
        $indice = IndiceFinanceiro::findByMd5($data->id_indice) ?: IndiceFinanceiro::find($data->id_indice);

        if (!$indice) {
            $this->message->warning("Índice não encontrado");
            Redirect::referer();
            return;
        }

        if (!$data->has("valor") || !$data->has("data_referencia")) {
            $this->message->warning("Informe o valor e a data de referência");
            Redirect::referer();
            return;
        }

        $payload = [
            "id_indice"       => $indice->id,
            "valor"           => $data->valor,
            "data_referencia" => $data->data_referencia,
            "fonte"           => $data->has("fonte") && $data->fonte !== "" ? $data->fonte : null,
            "observacao"      => $data->has("observacao") && $data->observacao !== "" ? $data->observacao : null,
            "created_by"      => $this->user->uid,
        ];

        IndiceFinanceiroHistorico::create($payload);

        $this->message->success("Registro adicionado com sucesso");
        $this->router->redirect("admin.indice.financeiro.editar", ["id" => md5((string) $indice->id)]);
    }

    public function historicoEdit(Request $request): void
    {
        $this->authorize("indice_financeiro_editar");

        $data      = new Data($request->all());
        $historico = IndiceFinanceiroHistorico::findByMd5($data->id) ?: IndiceFinanceiroHistorico::find($data->id);

        if (!$historico) {
            echo json_encode(["error" => true, "message" => "Registro não encontrado."], JSON_UNESCAPED_UNICODE);
            return;
        }

        $indice = IndiceFinanceiro::find($historico->id_indice);

        echo $this->view->render("admin/indice_financeiro/historico_form", [
            "csrf"       => $this->csrf->generate(),
            "historico"  => $historico,
            "indice"     => $indice,
            "url_action" => $this->router->route("admin.indice.financeiro.historico.update"),
        ]);
    }

    public function historicoUpdate(Request $request): void
    {
        $this->authorize("indice_financeiro_editar");

        $data      = new Data($request->all());
        $historico = IndiceFinanceiroHistorico::findByMd5($data->id) ?: IndiceFinanceiroHistorico::find($data->id);

        if (!$historico) {
            $this->message->warning("Registro não encontrado");
            Redirect::referer();
            return;
        }

        if (!$data->has("valor") || !$data->has("data_referencia")) {
            $this->message->warning("Informe o valor e a data de referência");
            Redirect::referer();
            return;
        }

        $payload = [
            "valor"           => $data->valor,
            "data_referencia" => $data->data_referencia,
            "fonte"           => $data->has("fonte") && $data->fonte !== "" ? $data->fonte : null,
            "observacao"      => $data->has("observacao") && $data->observacao !== "" ? $data->observacao : null,
            "updated_by"      => $this->user->uid,
        ];

        IndiceFinanceiroHistorico::updateBy($historico->id, $payload);

        $this->message->success("Registro atualizado com sucesso");
        $this->router->redirect("admin.indice.financeiro.editar", ["id" => md5((string) $historico->id_indice)]);
    }

    public function historicoDelete(Request $request): void
    {
        $this->authorize("indice_financeiro_excluir");

        header("Content-Type: application/json; charset=utf-8");

        $data      = new Data($request->all());
        $historico = IndiceFinanceiroHistorico::findByMd5($data->id) ?: IndiceFinanceiroHistorico::find($data->id);

        if (!$historico) {
            echo json_encode(["error" => true, "message" => "Registro não encontrado."], JSON_UNESCAPED_UNICODE);
            return;
        }

        IndiceFinanceiroHistorico::deleteById($historico->id);

        echo json_encode(["error" => false, "message" => "Registro removido com sucesso."], JSON_UNESCAPED_UNICODE);
    }
}
