<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\Redirect;
use App\Core\Request;
use App\Models\Cliente;
use App\Models\ClienteSituacao;

class ClienteSituacaoController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title" => "Situações",
            "active_menu" => "clientes-situacoes",
            "page" => [
                "title" => "Situações",
                "desc" => "Cadastre as situações usadas nos clientes",
            ],
            "uppers"   => implode(",", ClienteSituacao::getUppers()),
            "required" => implode(",", ClienteSituacao::getRequired()),
        ]);
    }

    public function index(): void
    {
        $this->authorize("cliente_situacao_gerenciar");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Clientes" => ["url" => $this->router->route("admin.cliente.index"), "current" => false],
                "Situações" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Situações",
                "desc" => "Cadastre as situações usadas nos clientes",
            ],
        ]);

        $situacoes = ClienteSituacao::orderBy("descricao")->get();

        foreach ($situacoes as $situacao) {
            $situacao->hash = md5((string) $situacao->id);
            $situacao->clientes = Cliente::where("id_situacao", "=", $situacao->id)->count();
            $situacao->badge = $this->badgeColor($situacao->descricao, $situacao->cor ?: "#6c757d");
            $situacao->disabled = "";
            $situacao->title = $situacao->clientes > 0 ? "Existem clientes vinculados à esta situação" : "Excluir situação";
            $situacao->action = $situacao->clientes > 0 ? "" : 'onclick="Delete(\'clientes/situacoes/delete\', \'' . $situacao->id . '\')"';
        }

        $permissao = [
            "cliente" => $this->auth->allow("cliente_gerenciar"),
            "inserir" => $this->auth->allow("cliente_situacao_inserir"),
            "editar" => $this->auth->allow("cliente_situacao_editar"),
            "excluir" => $this->auth->allow("cliente_situacao_excluir"),
        ];

        echo $this->view->render("admin/cliente/situacao/index", [
            "dados" => $situacoes,
            "permissao" => $permissao,
        ]);
    }

    public function new(): void
    {
        $this->authorize("cliente_situacao_inserir");

        echo $this->view->render("admin/cliente/situacao/form", [
            "csrf" => $this->csrf->generate(),
            "situacao" => false,
            "url_action" => $this->router->route("admin.cliente.situacao.insert"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("cliente_situacao_inserir");

        $data = new Data($request->all());

        if (!$data->has("descricao")) {
            $this->message->warning("Informe a descrição");
            Redirect::referer();
        }

        $payload = $data->all();
        unset($payload["csrf"], $payload["id"]);
        $payload["ativo"] = isset($payload["ativo"]) ? (int) $payload["ativo"] : 1;
        $payload["created_by"] = $this->user->uid;

        ClienteSituacao::create($payload);

        $this->message->success("Situação cadastrada com sucesso");
        $this->router->redirect("admin.cliente.situacao.index");
    }

    public function edit(Request $request): void
    {
        $this->authorize("cliente_situacao_editar");

        $data = new Data($request->all());
        $situacao = ClienteSituacao::find($data->id);

        if (!$situacao) {
            $this->message->warning("Situação não encontrada");
            $this->router->redirect("admin.cliente.situacao.index");
        }

        echo $this->view->render("admin/cliente/situacao/form", [
            "csrf" => $this->csrf->generate(),
            "situacao" => $situacao,
            "url_action" => $this->router->route("admin.cliente.situacao.update"),
        ]);
    }

    public function update(Request $request): void
    {
        $this->authorize("cliente_situacao_editar");

        $data = new Data($request->all());
        $situacao = ClienteSituacao::find($data->id) ?: ClienteSituacao::findByMd5($data->id);

        if (!$situacao) {
            $this->message->warning("Situação não encontrada");
            Redirect::referer();
        }

        if (!$data->has("descricao")) {
            $this->message->warning("Informe a descrição");
            Redirect::referer();
        }

        $payload = $data->all();
        unset($payload["csrf"], $payload["id"]);
        $payload["ativo"] = isset($payload["ativo"]) ? (int) $payload["ativo"] : 0;
        $payload["updated_by"] = $this->user->uid;

        ClienteSituacao::updateBy($situacao->id, $payload);

        $this->message->success("Situação atualizada com sucesso");
        $this->router->redirect("admin.cliente.situacao.index");
    }

    public function delete(Request $request): void
    {
        $this->authorize("cliente_situacao_excluir");

        $data = new Data($request->all());
        $situacao = ClienteSituacao::find($data->id) ?: ClienteSituacao::findByMd5($data->id);

        if (!$situacao) {
            $this->message->warning("Situação não encontrada");
            Redirect::referer();
            return;
        }

        if (Cliente::where("id_situacao", "=", $situacao->id)->count() > 0) {
            $this->message->warning("Existem clientes vinculados à esta situação");
            Redirect::referer();
            return;
        }

        ClienteSituacao::deleteById($situacao->id);

        $this->message->success("Situação removida com sucesso");
        Redirect::referer();
    }

    private function badgeColor(string $label, string $color): string
    {
        $color = trim($color) ?: "#6c757d";
        $textColor = colorContrast($color);

        return '<span class="badge filled-outlined" style="background:' . htmlspecialchars($color, ENT_QUOTES, "UTF-8") . ';border-color:' . htmlspecialchars($color, ENT_QUOTES, "UTF-8") . ';color:' . htmlspecialchars($textColor, ENT_QUOTES, "UTF-8") . ';">' . htmlspecialchars($label, ENT_QUOTES, "UTF-8") . '</span>';
    }
}
