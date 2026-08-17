<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\DB;
use App\Core\Redirect;
use App\Core\Request;
use App\Models\ModeloDemonstrativo;
use App\Models\TipoDemonstrativo;

class TipoDemonstrativoController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title"       => "Tipos de Demonstrativo",
            "active_menu" => "configuracoes-dre-tipo",
            "page"        => [
                "title" => "Tipos de Demonstrativo",
                "desc"  => "Cadastre os tipos de demonstrativo financeiro (DRE, DFC, BP...)",
            ],
        ]);
    }

    public function index(): void
    {
        $this->authorize("tipo_demonstrativo_gerenciar");

        $this->view->addData([
            "breadcrumb" => [
                "Configurações"           => ["url" => false, "current" => false],
                "Tipos de Demonstrativo"  => ["url" => false, "current" => true],
            ],
        ]);

        echo $this->view->render("admin/tipo_demonstrativo/index", [
            "dados"     => TipoDemonstrativo::listar(),
            "permissao" => [
                "inserir" => $this->auth->allow("tipo_demonstrativo_inserir"),
                "editar"  => $this->auth->allow("tipo_demonstrativo_editar"),
                "excluir" => $this->auth->allow("tipo_demonstrativo_excluir"),
            ],
            "csrf" => $this->csrf->generate(),
        ]);
    }

    public function new(): void
    {
        $this->authorize("tipo_demonstrativo_inserir");

        echo $this->view->render("admin/tipo_demonstrativo/form", [
            "csrf"       => $this->csrf->generate(),
            "tipo"       => null,
            "url_action" => $this->router->route("admin.dre.tipo.insert"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("tipo_demonstrativo_inserir");

        $data = new Data($request->all());

        if (!$data->has("nome") || !$data->has("sigla")) {
            $this->message->warning("Informe o nome e a sigla do tipo de demonstrativo");
            Redirect::referer();
            return;
        }

        $sigla = trim((string) $data->sigla);

        if (TipoDemonstrativo::existeSigla($sigla)) {
            $this->message->warning("Já existe um tipo de demonstrativo com esta sigla");
            Redirect::referer();
            return;
        }

        $ordem = DB::table("tipo_demonstrativo")->where("trash", "=", 0)->count();
        $nome  = trim((string) $data->nome);

        DB::transaction(function () use ($sigla, $nome, $ordem) {
            TipoDemonstrativo::create([
                "nome"       => $nome,
                "sigla"      => $sigla,
                "ordem"      => $ordem,
                "trash"      => 0,
                "created_by" => $this->user->uid,
            ]);

            ModeloDemonstrativo::criarPadraoParaTipo($sigla, $nome, $this->user->uid);
        });

        $this->message->success("Tipo de demonstrativo cadastrado com sucesso");
        $this->router->redirect("admin.dre.tipo.index");
    }

    public function edit(Request $request): void
    {
        $this->authorize("tipo_demonstrativo_editar");

        $data = new Data($request->all());
        $tipo = TipoDemonstrativo::find($data->id);

        if (!$tipo) {
            $this->message->warning("Tipo de demonstrativo não encontrado");
            $this->router->redirect("admin.dre.tipo.index");
            return;
        }

        echo $this->view->render("admin/tipo_demonstrativo/form", [
            "csrf"       => $this->csrf->generate(),
            "tipo"       => $tipo,
            "url_action" => $this->router->route("admin.dre.tipo.update"),
        ]);
    }

    public function update(Request $request): void
    {
        $this->authorize("tipo_demonstrativo_editar");

        $data = new Data($request->all());
        $tipo = TipoDemonstrativo::find($data->id);

        if (!$tipo) {
            $this->message->warning("Tipo de demonstrativo não encontrado");
            Redirect::referer();
            return;
        }

        if (!$data->has("nome") || !$data->has("sigla")) {
            $this->message->warning("Informe o nome e a sigla do tipo de demonstrativo");
            Redirect::referer();
            return;
        }

        $sigla = trim((string) $data->sigla);

        $outraComMesmaSigla = DB::table("tipo_demonstrativo")
            ->where("sigla", "=", $sigla)
            ->where("id", "!=", $tipo->id)
            ->where("trash", "=", 0)
            ->count();

        if ($outraComMesmaSigla > 0) {
            $this->message->warning("Já existe outro tipo de demonstrativo com esta sigla");
            Redirect::referer();
            return;
        }

        TipoDemonstrativo::updateBy($tipo->id, [
            "nome"       => trim((string) $data->nome),
            "sigla"      => $sigla,
            "ordem"      => (int) ($data->ordem ?? $tipo->ordem),
            "updated_by" => $this->user->uid,
        ]);

        $this->message->success("Tipo de demonstrativo atualizado com sucesso");
        $this->router->redirect("admin.dre.tipo.index");
    }

    public function delete(Request $request): void
    {
        $this->authorize("tipo_demonstrativo_excluir");

        $data = new Data($request->all());
        $tipo = TipoDemonstrativo::find($data->id);

        if (!$tipo) {
            $this->message->warning("Tipo de demonstrativo não encontrado");
            Redirect::referer();
            return;
        }

        $contasVinculadas = DB::table("dre_conta")
            ->where("tipo_demonstrativo", "=", $tipo->sigla)
            ->where("trash", "=", 0)
            ->count();

        if ($contasVinculadas > 0) {
            $this->message->warning("Existem contas cadastradas com este tipo. Remova-as antes de excluir.");
            Redirect::referer();
            return;
        }

        TipoDemonstrativo::updateBy($tipo->id, [
            "trash"      => 1,
            "updated_by" => $this->user->uid,
        ]);

        $this->message->success("Tipo de demonstrativo removido com sucesso");
        Redirect::referer();
    }
}
