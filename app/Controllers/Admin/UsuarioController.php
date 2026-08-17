<?php

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\Date;
use App\Core\Password;
use App\Core\Request;
use App\Core\Redirect;
use App\Models\Usuario;
use App\Models\UsuarioHistorico;
use App\Models\UsuarioPerfil;
use App\Models\UsuarioPreferencia;
use App\Services\UsuarioPermissaoService;
use App\Services\UsuarioService;

class UsuarioController extends ControllerAdmin
{
    protected string $mediapath;

    public function __construct()
    {
        parent::__construct();

        $this->mediapath = Usuario::getMediaPath();

        $this->view->addData([
            "title" => "Usuários",
            "active_menu" => "usuarios-usuarios",
            "page_title" => "Usuários do Sistema",
            "page_desc" => "Gerencie os usuários do sistema",
            "page" => [
                "title" => "Usuários do Sistema",
                "desc" => "Gerencie os usuários do sistema",
            ],
            "uppers" => implode(",", Usuario::getUppers()),
            "mediapath" => $this->mediapath,
        ]);
    }

    public function checkLogin()
    {
        $data = new Data($_GET);

        if (!$data->has("login")) {
            echo "false";
            return;
        }

        $query = Usuario::where("login", "=", trim($data->login));

        if ($data->has("id")) {
            $query->where("id", "!=", $data->id);
        }

        $exists = $query->first();

        echo $exists ? "false" : "true";
    }

    public function index()
    {
        $this->authorize("usuario_gerenciar");
        $this->auth->allow("usuario_gerenciar", true);

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Usuarios" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Usuários do Sistema",
                "desc" => "Gerencie os usuários do sistema",
            ],
        ]);

        $usuarios = Usuario::all();
        $profiles = UsuarioPerfil::all();
        $profilesById = [];

        $permissao = [
            "inserir" => $this->auth->allow("usuario_inserir"),
            "editar" => $this->auth->allow("usuario_editar"),
            "excluir" => $this->auth->allow("usuario_excluir"),
        ];

        foreach ($profiles as $profile) {
            $profilesById[(int) $profile->id] = $profile;
        }

        foreach ($usuarios as $u) {
            $u->foto_html = $u->foto
                ? '<img width="36" height="36" class="rounded-circle" src="' . $this->mediapath . $u->foto . '">'
                : '<i class="fa fa-user-circle text-secondary fa-2x"></i>';

            $u->status_print = $u->statusBadge();
            $u->hash = $u->hash();
            $u->perfil_print = "-";

            $profileId = (int) ($u->id_perfil ?? 0);
            $currentPermissions = UsuarioPermissaoService::selectedIds($u->permissoes ?? []);

            if ($profileId > 0 && isset($profilesById[$profileId])) {
                $profile = $profilesById[$profileId];
                $profilePermissions = UsuarioPermissaoService::selectedIds($profile->permissoes ?? []);

                sort($currentPermissions);
                sort($profilePermissions);

                $isModified = $currentPermissions !== $profilePermissions;
                $u->perfil_print = $profile->nome . ($isModified ? ' (modificado)' : '');
            }

            if (!$permissao["excluir"] || $u->id == $this->user->uid) {
                $u->disabled = "disabled";
                $u->action = "";
                $u->title = "Não permitido";
            } else {
                $u->disabled = "";
                $u->action = 'onclick="Delete(null, \'' . $u->hash . '\')"';
                $u->title = "Excluir usuário";
            }
        }

        echo $this->view->render("admin/usuario/lista", [
            "dados" => $usuarios,
            "permissao" => $permissao,
        ]);
    }

    public function history(Request $request)
    {
        $this->authorize("usuario_historico");

        $data = new Data($request->all());

        if ($data->has("id")) {
            $usuario = Usuario::findByMd5($data->id);

            if ($usuario) {
                $historico = UsuarioHistorico::where("id_usuario", "=", $usuario->id);
            } else {
                $this->message->flash("Usuário não encontrado", "danger");
                $this->router->redirect("admin.usuario.index");
            }
        } else {
            $historico = UsuarioHistorico::leftJoin("usuario as u", "uh.id_usuario", "=", "u.id")
                ->select("uh.*", "u.login as login_usuario");
        }

        $get = new Data($_GET);

        if ($get->has("p")) {
            if ($get->p != "todo") {
                if ($get->start && $get->end) {
                    $historico = $historico->whereBetween("uh.data", $get->start, $get->end);
                    $period_print = "De " . datebr($get->start) . " a " . datebr($get->end);
                } elseif ($get->start) {
                    $historico = $historico->where("uh.data", ">=", $get->start);
                    $period_print = "Desde " . datebr($get->start);
                } elseif ($get->end) {
                    $historico = $historico->where("uh.data", "<=", $get->end);
                    $period_print = "Ate " . datebr($get->end);
                } else {
                    $periods = Date::periods();
                    $dates = $periods[$get->p];
                    $historico = $historico->whereBetween("uh.data", $dates[0], $dates[1]);
                    $period_print = "De " . datebr($dates[0]) . " a " . datebr($dates[1]);
                }
            } else {
                $period_print = "Todo o Período";
            }
        } else {
            $period_print = "Todo o Período";
        }

        $historico = $historico->orderBy("uh.data", "desc")->get();

        if ($historico) {
            foreach ($historico as $h) {
                $h->data = datetimebr($h->data);
                $h->sis = limitaTexto($h->sistema, 30);
                $h->status_print = $h->status == "error"
                    ? '<span class="badge filled-outlined bg-danger">Erro</span>'
                    : '<span class="badge filled-outlined bg-success">Sucesso</span>';

                if (!$data->has("id")) {
                    $h->usuario_print = trim(($h->login_usuario ?? "") . ($h->login ? " (" . $h->login . ")" : ""));
                }
            }
        }

        $breadcrumb = [
            "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
            "Usuarios" => ["url" => $this->router->route("admin.usuario.index"), "current" => false],
        ];

        if ($data->has("id")) {
            $breadcrumb["Editar Usuario"] = [
                "url" => $this->router->route("admin.usuario.editar", ["id" => $data->id]),
                "current" => false,
            ];
        }

        $breadcrumb["Histórico de Login"] = ["url" => false, "current" => true];

        echo $this->view->render("admin/usuario/historico", [
            "page" => [
                "title" => isset($usuario) ? "Histórico de Login" : "Histórico de Acessos",
                "desc" => isset($usuario)
                    ? "Visualize os acessos e tentativas de login do usuário selecionado"
                    : "Acompanhe os acessos e tentativas de login do sistema",
            ],
            "breadcrumb" => $breadcrumb,
            "usuario" => $usuario ?? false,
            "historico" => $historico,
            "periodo_print" => $period_print,
            "url_back" => isset($usuario) && !empty($usuario->id)
                ? $this->router->route("admin.usuario.editar", ["id" => $usuario->id])
                : $this->router->route("admin.usuario.index"),
        ]);
    }

    public function new()
    {
        $this->authorize("usuario_inserir");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Usuários" => ["url" => $this->router->route("admin.usuario.index"), "current" => false],
                "Novo Usuário" => ["url" => false, "current" => true],
            ],
            "page_title" => "Cadastrar Usuário",
            "page_desc" => "Preencha os dados do novo usuário e defina suas permissões iniciais",
            "page" => [
                "title" => "Cadastrar Usuário",
                "desc" => "Preencha os dados do novo usuário e defina suas permissões iniciais",
            ],
        ]);

        $permissao["permissoes"] = $this->auth->allow("usuario_permissoes");

        echo $this->view->render("admin/usuario/novo", [
            "csrf" => $this->csrf->generate(),
            "permissao" => $permissao,
            "permissoes" => UsuarioPermissaoService::grouped(),
            "perfis" => $this->availableProfiles(),
            "url_back" => $this->router->route("admin.usuario.index"),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize("usuario_inserir");

        $data = new Data($request->all());
        $data->nullIfEmpty("id_perfil");
        $data->add("permissoes", UsuarioPermissaoService::selectedIds($data->permissoes ?? []));
        $data->toJson("permissoes");

        $id = UsuarioService::criar($data->all(), $this->user->uid);

        if (!empty($_FILES["foto"]["name"])) {
            $foto = UsuarioService::processarFoto($_FILES["foto"]);
            Usuario::updateBy($id, ["foto" => $foto]);
        }

        UsuarioPreferencia::create(["id_user" => $id]);

        $this->cache->clearGroup("menu_admin_usuario");

        $this->log->app("insert", [
            "id" => $id,
            "user" => $this->user->uid,
            "tabela" => "usuario",
        ]);

        $this->message->flash(8);
        $this->router->redirect("admin.usuario.index");
    }

    public function edit(Request $request)
    {
        $this->authorize("usuario_editar", $this->router->route("admin.usuario.index"));

        $data = new Data($request->all());
        $usuario = Usuario::find($data->id);

        if (!$usuario) {
            $this->message->flash("Usuário não encontrado", "danger", true);
            $this->router->redirect("admin.usuario.index");
        }

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Usuários" => ["url" => $this->router->route("admin.usuario.index"), "current" => false],
                "Editar Usuário" => ["url" => false, "current" => true],
            ],
            "page_title" => "Editar Usuário",
            "page_desc" => "Atualize os dados cadastrais e as permissões do usuário selecionado",
            "page" => [
                "title" => "Editar Usuario",
                "desc" => "Atualize os dados cadastrais e as permissões do usuário selecionado",
            ],
        ]);

        $current = json_decode($usuario->permissoes ?? "[]", true);

        $permissao = [
            "permissoes" => $this->auth->allow("usuario_permissoes"),
            "historico" => $this->auth->allow("usuario_historico"),
        ];

        echo $this->view->render("admin/usuario/editar", [
            "permissao" => $permissao,
            "usuario" => $usuario,
            "permissoes" => UsuarioPermissaoService::grouped(
                UsuarioPermissaoService::selectedIds($current)
            ),
            "perfis" => $this->availableProfiles(),
            "csrf" => $this->csrf->generate(),
            "url_back" => $this->router->route("admin.usuario.index"),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize("usuario_editar");

        $data = new Data($request->all(), false);
        $usuario = Usuario::findByMd5($data->id);

        if (!$usuario) {
            $this->message->danger("Usuario não encontrado");
            Redirect::referer();
        }

        $data->nullIfEmpty("id_perfil");
        $data->add("permissoes", UsuarioPermissaoService::selectedIds($data->permissoes ?? []));
        $data->toJson("permissoes");
        $data->remove(["id"]);

        UsuarioService::atualizar($usuario->id, $data->all());

        if (!empty($_FILES["foto"]["name"])) {
            $foto = UsuarioService::processarFoto($_FILES["foto"], $usuario->foto);
            Usuario::updateBy($usuario->id, ["foto" => $foto]);
        }

        $this->renew($usuario->id);

        $this->log->app("update", [
            "id" => $data->id,
            "user" => $this->user->uid,
            "tabela" => "empresa",
        ]);

        $this->message->flash(10);
        $this->router->redirect("admin.usuario.editar", ["id" => $usuario->id]);
    }

    private function availableProfiles(): array
    {
        return UsuarioPerfil::orderBy("nome")->get();
    }

    private function renew($id)
    {
        if ($this->user->uid == $id) {
            $usuarioAtualizado = Usuario::find($id);
            $this->auth->renew($usuarioAtualizado->permissoes);
            $this->cache->clear("menu_admin_usuario_" . $id);
        }
    }

    public function delete(Request $request)
    {
        $this->authorize("usuario_excluir", $this->router->route("admin.usuario.index"));

        $data = new Data($request->all());
        $usuario = Usuario::findByMd5($data->id);

        if (!$usuario) {
            $this->message->danger("Usuário não encontrado");
            Redirect::referer();
        }

        Usuario::deleteById($usuario->id);

        $this->message->flash(12);
        Redirect::referer();
    }

    public function deletePhoto(Request $request)
    {
        $this->authorize("usuario_editar");

        $data = new Data($request->all());
        UsuarioService::removerFotoUsuario($data->id);

        $this->cache->clearGroup("menu_");

        $this->message->success("Foto removida com sucesso");
        Redirect::referer();
    }

    public function pass()
    {
        $breadcrumb = [
            "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
            "Editar sua senha" => ["url" => false, "current" => true],
        ];
        $passwordMinLength = (int) (Config::get("auth.security.password_policy.min_length") ?? 5);

        $this->view->addData([
            "active_menu" => "senha",
            "page" => [
                "title" => "Alterar Senha do Sistema",
                "desc" => "Editar sua senha",
            ],
        ]);

        echo $this->view->render("admin/usuario/senha", [
            "breadcrumb" => $breadcrumb,
            "csrf" => $this->csrf->generate(),
            "password_min_length" => $passwordMinLength,
            "url_back" => $this->router->route("admin.home"),
        ]);
    }

    public function verifyPass(Request $request)
    {
        $data = new Data($request->all());

        if (!$data->has("old")) {
            echo "false";
            return;
        }

        $columnPassword = $this->auth->getColumnPassword();
        $user = Usuario::find($this->user->uid);

        $valid = $user && Password::verify(
            $data->old,
            $user->{$columnPassword}
        );

        echo $valid ? "true" : "false";
    }

    public function updatePass(Request $request)
    {
        $data = new Data($request->all());
        $minLength = (int) (Config::get("auth.security.password_policy.min_length") ?? 5);

        if (!$data->has("old")) {
            $this->message->warning("Informe sua senha atual");
            Redirect::referer();
        }

        if (
            !$data->has("senha") ||
            !$data->has("confirm") ||
            $data->senha !== $data->confirm
        ) {
            $this->message->warning("As senhas não conferem");
            Redirect::referer();
        }

        if (mb_strlen((string) $data->senha) < $minLength) {
            $this->message->warning("A nova senha deve ter pelo menos {$minLength} caracteres");
            Redirect::referer();
        }

        $columnPassword = $this->auth->getColumnPassword();
        $user = Usuario::find($this->user->uid);

        if (!$user || !Password::verify($data->old, $user->{$columnPassword})) {
            $this->message->warning("A senha atual informada está incorreta");
            Redirect::referer();
        }

        $data->password("senha");
        $data->remove(["old", "confirm"]);

        $userId = $this->user->uid;

        $updated = Usuario::updateBy($userId, [
            "senha" => $data->senha,
            "token" => substr(hash("sha256", microtime(true) . random_int(1, PHP_INT_MAX)), 0, 64),
        ]);

        if (!$updated) {
            $this->message->danger("Erro ao atualizar a senha");
            Redirect::referer();
        }

        $this->auth->revokeAllSessionsForUser($userId, false, "password_changed");

        $this->log->app("Senha alterada", [
            "user" => $userId,
            "tabela" => "usuario",
        ]);

        $this->auth->logout();
        $this->message->flash("Senha alterada com sucesso. Entre novamente com a nova senha.", "success", true);
        $this->router->redirect("admin.login");
    }
}
