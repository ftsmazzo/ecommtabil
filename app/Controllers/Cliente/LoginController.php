<?php

namespace App\Controllers\Cliente;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Models\User;
use App\Services\AuthHistoricoService;
use App\Services\LoginService;

class LoginController extends Controller
{
    protected $config;
    protected $messages;
    protected $animate_show;
    protected $animate_error;
    protected $authGuard;
    protected $authTable;
    protected $historyTable;
    protected $loginRoute;
    protected $historyClass = AuthHistoricoService::class;
    protected LoginService $loginService;
    protected array $loginViewData;

    public function __construct()
    {
        parent::__construct();

        $this->config = Config::get("auth");

        $this->view->addData(["message" => $this->message]);

        $this->animate_show = "animate__slideInDown animate__faster";
        $this->animate_error = "animate__shakeX";

        $this->authGuard = "user";
        $this->auth->setGuard($this->authGuard);

        $this->authTable = $this->auth->getTable();
        $this->loginRoute = $this->auth->getRouteLogin();
        $this->historyTable = $this->auth->getTableHistory();

        $this->loginService = new LoginService($this->auth, $this->router, $this->message, $this->session, $this->log);
        $backgrounds = ["login.jpg"];
        $rand = array_rand($backgrounds);
        $backgroundFile = $backgrounds[$rand] ?? "";

        $this->loginViewData = [
            "template" => "fluid",
            "logo" => layout((string) (Config::get("app.sceneries.cliente.layout.logo_login", "logo-dark.png"))),
            "logo_height" => 150,
            "background" => $backgroundFile !== "" ? layout($backgroundFile) : false,
        ];
    }

    public function index(): void
    {
        if ($this->auth->auth()) {
            $this->router->redirect($this->auth->getRouteRedirect());
        }

        $action = $this->router->route($this->auth->getRouteSignin());
        $input = $this->loginService->makeInputConfig([
            "user_label" => "Usuario",
            "user_placeholder" => "Digite seu usuario",
            "pass_label" => "Senha",
            "pass_placeholder" => "Digite sua senha",
        ]);
        $recaptcha = Config::get("recaptcha");

        $params = array_merge($this->getCommonLoginViewData(), [
            "title" => "| Login",
            "login_box_title" => "Painel Administrativo",
            "login_box_desc" => "Entre com seu usuario e senha para acessar o painel administrativo.",
            "input" => $input,
            "action" => $action,
            "recaptcha" => $recaptcha["auth"] ? $recaptcha["site_key"] : false,
            "recuperar_senha" => false,
        ]);

        $this->session->unset("animate");

        echo $this->view->render("app/login/index", $params);
    }

    public function login(Request $request): void
    {
        $this->loginService->authenticate($request, [
            "auth_guard" => $this->authGuard,
            "history_table" => $this->historyTable,
            "login_route" => $this->loginRoute,
            "animate_error" => $this->animate_error,
            "history_class" => $this->historyClass,
            "remember_field" => "manter",
            "on_success" => function ($user) {
                if (!$user) {
                    $this->message->flash("Sessao invalida. Faca login novamente.", "danger", true);
                    $this->router->redirect($this->loginRoute);
                }

                $fullUser = User::find($user->uid);
                $extra = !empty($fullUser->estabelecimentos) ? json_decode($fullUser->estabelecimentos) : [];
                $this->auth->setExtraData(["estabelecimentos" => array_filter($extra)]);
            },
        ]);
    }

    public function logout(): void
    {
        $this->auth->setGuard($this->authGuard);
        $user = $this->auth->user() ?? null;

        if ($user) {
            $ip = $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";

            if ($this->auth->getHistory()) {
                $columnLogin = $this->auth->getColumnLogin();
                $this->historyClass::registrar(
                    $this->historyTable,
                    [
                        "id_usuario" => $user->uid,
                        "login" => $user->{$columnLogin},
                        "acao" => "logout",
                        "status" => "success",
                        "motivo" => "Encerramento de sessao",
                    ]
                );
            }

            $this->log->app("Logout efetuado", ["user" => $user->uid, "ip" => $ip]);
        }

        $this->auth->logout();
        $this->message->flash(6);
        $this->router->redirect($this->loginRoute);
    }

    private function getCommonLoginViewData(): array
    {
        return array_merge($this->loginViewData, [
            "csrf" => $this->csrf->generate(),
        ]);
    }
}
