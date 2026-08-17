<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\Redirect;
use App\Core\Request;
use App\Models\Usuario;
use App\Models\UsuarioPerfil;
use App\Services\UsuarioPermissaoService;

class UsuarioPerfilController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title" => "Perfis de Acesso",
            "active_menu" => "usuarios-perfis",
            "page" => [
                "title" => "Perfis de Acesso",
                "desc" => "Cadastre perfis para parametrizar permissões dos usuários",
            ],
        ]);
    }

    public function index(): void
    {
        $this->authorize("usuario_perfil_gerenciar");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Perfis de Acesso" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Perfis de Acesso",
                "desc" => "Cadastre perfis para parametrizar permissões dos usuários",
            ],
        ]);

        $profiles = UsuarioPerfil::orderBy("nome")->get();

        foreach ($profiles as $profile) {
            $profile->usuarios = Usuario::where("id_perfil", "=", $profile->id)->count();
            $profile->permissoes_total = count(UsuarioPermissaoService::selectedIds($profile->permissoes));
            $profile->hash = md5($profile->id);
            $profile->disabled = $profile->usuarios > 0 ? "disabled" : "";
            $profile->title = $profile->usuarios > 0
                ? "Existem usuários vinculados à este perfil"
                : "Excluir perfil";
            $profile->action = $profile->usuarios > 0
                ? ""
                : 'onclick="Delete(null, \'' . $profile->hash . '\')"';
        }

        $permissao = [
            "inserir" => $this->auth->allow("usuario_perfil_inserir"),
            "editar"  => $this->auth->allow("usuario_perfil_editar"),
            "excluir" => $this->auth->allow("usuario_perfil_excluir"),
        ];

        echo $this->view->render("admin/perfil/lista", [
            "dados" => $profiles,
            "permissao" => $permissao,
        ]);
    }

    public function new(): void
    {
        $this->authorize("usuario_perfil_inserir");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Perfis de Acesso" => ["url" => $this->router->route("admin.perfil.index"), "current" => false],
                "Novo Perfil" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Cadastrar Perfil",
                "desc" => "Monte um perfil base para agilizar o preenchimento de permissões",
            ],
        ]);

        echo $this->view->render("admin/perfil/novo", [
            "csrf" => $this->csrf->generate(),
            "permissoes" => UsuarioPermissaoService::grouped(),
            "url_back" => $this->router->route("admin.perfil.index"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("usuario_perfil_inserir");

        $data = new Data($request->all());

        if (!$data->has("nome")) {
            $this->message->warning("Informe o nome do perfil");
            Redirect::referer();
        }

        $data->add("permissoes", UsuarioPermissaoService::selectedIds($data->permissoes ?? []));
        $data->toJson("permissoes");
        $data->remove(["csrf"]);
        $data->add("created_by", $this->user->uid);

        UsuarioPerfil::create($data->all());

        $this->message->success("Perfil cadastrado com sucesso");
        $this->router->redirect("admin.perfil.index");
    }

    public function edit(Request $request): void
    {
        $this->authorize("usuario_perfil_editar");

        $data = new Data($request->all());
        $profile = UsuarioPerfil::find($data->id);

        if (!$profile) {
            $this->message->warning("Perfil não encontrado");
            $this->router->redirect("admin.perfil.index");
        }

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Perfis de Acesso" => ["url" => $this->router->route("admin.perfil.index"), "current" => false],
                "Editar Perfil" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Editar Perfil",
                "desc" => "Atualize o perfil base utilizado para parametrizar permissões",
            ],
        ]);

        echo $this->view->render("admin/perfil/editar", [
            "csrf" => $this->csrf->generate(),
            "perfil" => $profile,
            "permissoes" => UsuarioPermissaoService::grouped(
                UsuarioPermissaoService::selectedIds($profile->permissoes)
            ),
            "usuarios_vinculados" => Usuario::where("id_perfil", "=", $profile->id)->count(),
            "url_back" => $this->router->route("admin.perfil.index"),
        ]);
    }

    public function update(Request $request): void
    {
        $this->authorize("usuario_perfil_editar");

        $data = new Data($request->all());
        $profile = UsuarioPerfil::findByMd5($data->id);

        if (!$profile) {
            $this->message->warning("Perfil não encontrado");
            Redirect::referer();
        }

        if (!$data->has("nome")) {
            $this->message->warning("Informe o nome do perfil");
            Redirect::referer();
        }

        $data->add("permissoes", UsuarioPermissaoService::selectedIds($data->permissoes ?? []));
        $data->toJson("permissoes");
        $data->remove(["csrf", "id"]);
        $data->add("updated_by", $this->user->uid);

        UsuarioPerfil::updateBy($profile->id, $data->all());

        $this->message->success("Perfil atualizado com sucesso");
        $this->router->redirect("admin.perfil.editar", ["id" => $profile->id]);
    }

    public function delete(Request $request): void
    {
        $this->authorize("usuario_perfil_excluir");

        $data = new Data($request->all());
        $profile = UsuarioPerfil::findByMd5($data->id);

        if (!$profile) {
            $this->message->warning("Perfil não encontrado");
            Redirect::referer();
        }

        if (Usuario::where("id_perfil", "=", $profile->id)->count() > 0) {
            $this->message->warning("Existem usuários vinculados à este perfil");
            Redirect::referer();
        }

        UsuarioPerfil::deleteById($profile->id);

        $this->message->success("Perfil removido com sucesso");
        Redirect::referer();
    }

    public function permissions(Request $request): void
    {
        $this->authorize("usuario_permissoes");

        $data = new Data($request->all());
        $profile = UsuarioPerfil::find($data->id);

        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(
            $profile ? UsuarioPermissaoService::selectedIds($profile->permissoes) : [],
            JSON_UNESCAPED_UNICODE
        );
    }
}
