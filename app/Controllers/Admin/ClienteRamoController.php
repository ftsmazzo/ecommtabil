<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\Redirect;
use App\Core\Request;
use App\Models\Cliente;
use App\Models\ClienteRamo;

class ClienteRamoController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title" => "Ramos",
            "active_menu" => "clientes-ramos",
            "page" => [
                "title" => "Ramos",
                "desc" => "Cadastre os ramos usados nos clientes",
            ],
        ]);
    }

    public function index(): void
    {
        $this->authorize("cliente_ramo_gerenciar");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Clientes" => ["url" => $this->router->route("admin.cliente.index"), "current" => false],
                "Ramos" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Ramos",
                "desc" => "Cadastre os ramos usados nos clientes",
            ],
        ]);

        $ramos = ClienteRamo::orderBy("descricao")->get();

        foreach ($ramos as $ramo) {
            $ramo->hash = md5((string) $ramo->id);
            $ramo->clientes = Cliente::where("id_ramo", "=", $ramo->id)->count();
            $ramo->disabled = "";
            $ramo->title = $ramo->clientes > 0 ? "Existem clientes vinculados à este ramo" : "Excluir ramo";
            $ramo->action = $ramo->clientes > 0 ? "" : 'onclick="Delete(\'clientes/ramos/delete\', \'' . $ramo->id . '\')"';
        }

        $permissao = [
            "cliente" => $this->auth->allow("cliente_gerenciar"),
            "inserir" => $this->auth->allow("cliente_ramo_inserir"),
            "editar" => $this->auth->allow("cliente_ramo_editar"),
            "excluir" => $this->auth->allow("cliente_ramo_excluir"),
        ];

        echo $this->view->render("admin/cliente/ramo/index", [
            "dados" => $ramos,
            "permissao" => $permissao,
        ]);
    }

    public function new(): void
    {
        $this->authorize("cliente_ramo_inserir");

        echo $this->view->render("admin/cliente/ramo/form", [
            "csrf" => $this->csrf->generate(),
            "ramo" => false,
            "url_action" => $this->router->route("admin.cliente.ramo.insert"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("cliente_ramo_inserir");

        $data = new Data($request->all());

        if (!$data->has("descricao")) {
            $this->message->warning("Informe a descrição");
            Redirect::referer();
            return;
        }

        $payload = $data->all();
        unset($payload["csrf"], $payload["id"]);
        $payload["created_by"] = $this->user->uid;

        ClienteRamo::create($payload);

        $this->message->success("Ramo cadastrado com sucesso");
        $this->router->redirect("admin.cliente.ramo.index");
    }

    public function edit(Request $request): void
    {
        $this->authorize("cliente_ramo_editar");

        $data = new Data($request->all());
        $ramo = ClienteRamo::find($data->id);

        if (!$ramo) {
            $this->message->warning("Ramo não encontrado");
            $this->router->redirect("admin.cliente.ramo.index");
        }

        echo $this->view->render("admin/cliente/ramo/form", [
            "csrf" => $this->csrf->generate(),
            "ramo" => $ramo,
            "url_action" => $this->router->route("admin.cliente.ramo.update"),
        ]);
    }

    public function update(Request $request): void
    {
        $this->authorize("cliente_ramo_editar");

        $data = new Data($request->all());
        $ramo = ClienteRamo::find($data->id) ?: ClienteRamo::findByMd5($data->id);

        if (!$ramo) {
            $this->message->warning("Ramo não encontrado");
            Redirect::referer();
            return;
        }

        if (!$data->has("descricao")) {
            $this->message->warning("Informe a descrição");
            Redirect::referer();
        }

        $payload = $data->all();
        unset($payload["csrf"], $payload["id"]);
        $payload["updated_by"] = $this->user->uid;

        ClienteRamo::updateBy($ramo->id, $payload);

        $this->message->success("Ramo atualizado com sucesso");
        $this->router->redirect("admin.cliente.ramo.index");
    }

    public function delete(Request $request): void
    {
        $this->authorize("cliente_ramo_excluir");

        $data = new Data($request->all());
        $ramo = ClienteRamo::find($data->id) ?: ClienteRamo::findByMd5($data->id);

        if (!$ramo) {
            $this->message->warning("Ramo não encontrado");
            Redirect::referer();
        }

        if (Cliente::where("id_ramo", "=", $ramo->id)->count() > 0) {
            $this->message->warning("Existem clientes vinculados à este ramo");
            Redirect::referer();
        }

        ClienteRamo::deleteById($ramo->id);

        $this->message->success("Ramo removido com sucesso");
        Redirect::referer();
    }
}
