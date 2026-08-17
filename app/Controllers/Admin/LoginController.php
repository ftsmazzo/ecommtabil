<?php

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Data;
use App\Core\Email;
use App\Core\Password;
use App\Core\Redirect;
use App\Core\Request;
use App\Models\PasswordResetToken;
use App\Models\Usuario;
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
    protected int $passwordMinLength;

    public function __construct()
    {
        parent::__construct();

        $this->config = Config::get("auth");

        $this->view->addData(["message" => $this->message]);

        $this->animate_show = "animate__slideInDown animate__faster";
        $this->animate_error = "animate__shakeX";

        $this->authGuard = "usuario";
        $this->auth->setGuard($this->authGuard);

        $this->authTable = $this->auth->getTable();
        $this->loginRoute = $this->auth->getRouteLogin();
        $this->historyTable = $this->auth->getTableHistory();
        $this->loginService = new LoginService($this->auth, $this->router, $this->message, $this->session, $this->log);
        $this->passwordMinLength = (int) (Config::get("auth.security.password_policy.min_length") ?? 5);

        // $backgrounds = [""];
        // $rand = array_rand($backgrounds);
        // $backgroundFile = $backgrounds[$rand] ?? "";

        $backgroundLogin = Config::get("app.sceneries.admin.layout.background_login");
        $backgroundFile = $backgroundLogin !== ""
            ? layout($backgroundLogin)
            : false;

        $this->loginViewData = [
            "template" => "box", // box ou fluid
            "logo" => layout((string) (Config::get("app.sceneries.admin.layout.logo_login", "logo.svg"))),
            "logo_height" => 100,
            "background" => $backgroundFile,
        ];
    }

    public function index(): void
    {
        if ($this->auth->auth()) {
            $this->router->redirect($this->auth->getRouteRedirect());
        }

        $action = $this->router->route($this->auth->getRouteSignin());
        $input = $this->loginService->makeInputConfig([
            "user_label" => "Usuário",
            "user_placeholder" => "Digite seu usuário",
            "pass_label" => "Senha",
            "pass_placeholder" => "Digite sua senha",
        ]);
        $params = array_merge($this->getCommonLoginViewData(), [
            "title" => "| Login",
            "login_box_title" => "Painel Administrativo",
            "login_box_desc" => false,
            "input" => $input,
            "action" => $action,
            "recuperar_senha" => $this->forgotPasswordEnabled()
                ? $this->router->route($this->auth->getRouteForgotPassword())
                : false,
        ]);

        $this->session->unset("animate");

        echo $this->view->render("app/login/index", $params);
    }

    public function password(): void
    {
        $this->ensureForgotPasswordEnabled();

        if ($this->auth->auth()) {
            $this->router->redirect($this->auth->getRouteRedirect());
        }

        echo $this->view->render("app/login/password", array_merge($this->getCommonLoginViewData(), [
            "action" => $this->router->route($this->auth->getRouteForgotPasswordRequest()),
            "login_url" => $this->router->route($this->auth->getRouteLogin()),
        ]));
    }

    public function passwordRequest(Request $request): void
    {
        $this->ensureForgotPasswordEnabled();

        $data = new Data($request->all());

        if (!$data->has("email")) {
            $this->message->flash("Informe seu e-mail para recuperar a senha", "danger", true);
            $this->router->redirect($this->auth->getRouteForgotPassword());
        }

        if (!$data->blackListed(["email"])) {
            $this->message->flash("Ocorreu um erro ao processar a recuperação de senha", "danger", true);
            $this->router->redirect($this->auth->getRouteForgotPassword());
        }

        $email = mb_strtolower(trim((string) $data->email), "UTF-8");
        $rateLimit = $this->auth->checkRateLimit("password_request", $email);

        if (empty($rateLimit["ok"])) {
            $this->message->flash("Muitas solicitacoes de recuperação foram feitas. Aguarde alguns minutos e tente novamente.", "danger", true);
            $this->router->redirect($this->auth->getRouteForgotPassword());
        }

        if (!$this->loginService->validateRecaptcha($data->recaptcha ?? null)) {
            $this->auth->hitRateLimit("password_request", $email);
            $this->message->flash("Não foi possivel validar a solicitação de recuperação. Tente novamente.", "danger", true);
            $this->router->redirect($this->auth->getRouteForgotPassword());
        }

        $this->auth->hitRateLimit("password_request", $email);
        $user = Usuario::where("email", "=", $email)->where("status", "=", 1)->first();

        if ($user && !empty($user->email)) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash("sha256", $token);
            $expiresAt = date("Y-m-d H:i:s", strtotime("+1 hour"));

            PasswordResetToken::where("guard", "=", $this->authGuard)
                ->where("user_id", "=", $user->id)
                ->delete();

            PasswordResetToken::create([
                "guard" => $this->authGuard,
                "user_id" => $user->id,
                "email" => $email,
                "token_hash" => $tokenHash,
                "expires_at" => $expiresAt,
                "requested_ip" => getip(),
            ]);

            $resetUrl = $this->router->route($this->auth->getRouteResetPassword(), ["token" => $token]);

            $mailer = new Email();
            $mailSent = $mailer
                ->subject(app("name") . " | Recuperação de senha")
                ->body(
                    $mailer->template(
                        "<p>Olá, {$user->nome}.</p>" .
                        "<p>Recebemos uma solicitação para redefinir sua senha.</p>" .
                        "<p><a href=\"{$resetUrl}\">Clique aqui para criar uma nova senha</a></p>" .
                        "<p>Se voce não solicitou essa alteração, ignore este e-mail.</p>" .
                        "<p>Este link expira em 1 hora.</p>"
                    )
                )
                ->to($email, $user->nome)
                ->send();

            if (!$mailSent) {
                $this->log->app("Falha ao enviar e-mail de recuperação de senha", [
                    "user" => $user->id,
                    "email" => $email,
                    "tabela" => $this->authTable,
                ]);
            }
        }

        $this->message->flash(
            "Se o e-mail informado estiver cadastrado e ativo, enviaremos um link para redefinir sua senha.",
            "success",
            true
        );
        $this->router->redirect($this->auth->getRouteForgotPassword());
    }

    public function passwordReset(Request $request): void
    {
        $this->ensureForgotPasswordEnabled();

        if ($this->auth->auth()) {
            $this->router->redirect($this->auth->getRouteRedirect());
        }

        $data = new Data($request->all());
        $token = trim((string) ($data->token ?? ""));
        $record = $this->findValidResetToken($token);

        if (!$record) {
            $this->message->flash("O link de redefinição de senha é inválido ou expirou.", "danger", true);
            $this->router->redirect($this->auth->getRouteForgotPassword());
        }

        echo $this->view->render("app/login/reset", array_merge($this->getCommonLoginViewData(), [
            "action" => $this->router->route($this->auth->getRouteResetPasswordUpdate()),
            "token" => $token,
            "login_url" => $this->router->route($this->auth->getRouteLogin()),
        ]));
    }

    public function passwordUpdate(Request $request): void
    {
        $this->ensureForgotPasswordEnabled();

        $data = new Data($request->all());

        if (!$data->has("token")) {
            $this->message->flash("Link de redefinição inválido.", "danger", true);
            $this->router->redirect($this->auth->getRouteForgotPassword());
        }

        if (!$data->has("senha") || !$data->has("confirm") || $data->senha !== $data->confirm) {
            $this->message->flash("As senhas informadas não conferem.", "danger", true);
            Redirect::referer();
        }

        if (mb_strlen((string) $data->senha) < $this->passwordMinLength) {
            $this->message->flash("A nova senha deve ter pelo menos {$this->passwordMinLength} caracteres.", "danger", true);
            Redirect::referer();
        }

        $token = (string) $data->token;
        $rateLimit = $this->auth->checkRateLimit("password_reset", $token);

        if (empty($rateLimit["ok"])) {
            $this->message->flash("Muitas tentativas de redefinicao foram feitas. Aguarde alguns minutos e tente novamente.", "danger", true);
            $this->router->redirect($this->auth->getRouteForgotPassword());
        }

        if (!$this->loginService->validateRecaptcha($data->recaptcha ?? null)) {
            $this->auth->hitRateLimit("password_reset", $token);
            $this->message->flash("Não foi possivel validar a redefinicao de senha. Tente novamente.", "danger", true);
            Redirect::referer();
        }

        $this->auth->hitRateLimit("password_reset", $token);
        $record = $this->findValidResetToken($token);

        if (!$record) {
            $this->message->flash("O link de redefinição de senha é inválido ou expirou.", "danger", true);
            $this->router->redirect($this->auth->getRouteForgotPassword());
        }

        $user = Usuario::find($record->user_id);

        if (!$user) {
            $this->message->flash("Usuário não encontrado para redefinição de senha.", "danger", true);
            $this->router->redirect($this->auth->getRouteForgotPassword());
        }

        $updated = Usuario::updateBy($user->id, [
            "senha" => Password::hash((string) $data->senha),
            "token" => substr(hash("sha256", microtime(true) . random_int(1, PHP_INT_MAX)), 0, 64),
        ]);

        if (!$updated) {
            $this->message->flash("Não foi possivel atualizar a senha.", "danger", true);
            Redirect::referer();
        }

        // A redefinicao invalida todas as sessoes ativas do usuario.
        $this->auth->revokeAllSessionsForUser($user->id, false, "password_reset");

        PasswordResetToken::updateBy($record->id, [
            "used_at" => date("Y-m-d H:i:s"),
        ]);

        PasswordResetToken::where("guard", "=", $this->authGuard)
            ->where("user_id", "=", $user->id)
            ->where("id", "!=", $record->id)
            ->delete();

        $this->historyClass::registrar(
            $this->historyTable,
            [
                "id_usuario" => $user->id,
                "login" => $user->login,
                "acao" => "password_reset",
                "status" => "success",
                "motivo" => "Senha redefinida por recuperação",
            ]
        );

        $this->log->app("Senha redefinida por recuperação", [
            "user" => $user->id,
            "tabela" => $this->authTable,
        ]);

        $this->auth->clearRateLimit("password_reset", $token);
        $this->message->flash("Senha redefinida com sucesso. Faça login com a nova senha.", "success", true);
        $this->router->redirect($this->loginRoute);
    }

    public function login(Request $request): void
    {
        $this->loginService->authenticate($request, [
            "auth_guard" => $this->authGuard,
            "history_table" => $this->historyTable,
            "login_route" => $this->loginRoute,
            "animate_error" => $this->animate_error,
            "history_class" => $this->historyClass,
            "remember_field" => "remember",
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
                        "motivo" => "Encerramento de sessão",
                    ]
                );
            }

            $this->log->app("Logout efetuado", ["user" => $user->uid, "ip" => $ip]);
        }

        $this->auth->logout();
        $this->message->flash(6);
        $this->router->redirect($this->loginRoute);
    }

    private function findValidResetToken(string $token): ?PasswordResetToken
    {
        if (!$token) {
            return null;
        }

        $tokenHash = hash("sha256", $token);

        return PasswordResetToken::where("guard", "=", $this->authGuard)
            ->where("token_hash", "=", $tokenHash)
            ->whereNull("used_at")
            ->where("expires_at", ">", date("Y-m-d H:i:s"))
            ->first();
    }

    private function forgotPasswordEnabled(): bool
    {
        return (bool) $this->auth->getFeature("forgot_password", false);
    }

    private function ensureForgotPasswordEnabled(): void
    {
        if ($this->forgotPasswordEnabled()) {
            return;
        }

        $this->log->app("Tentativa de acesso ao fluxo desabilitado de recuperação de senha", [
            "guard" => $this->authGuard,
            "ip" => getip(),
            "tabela" => $this->authTable,
        ]);

        $this->router->redirect($this->loginRoute);
    }

    private function getCommonLoginViewData(): array
    {
        return array_merge($this->loginViewData, [
            "csrf" => $this->csrf->generate(),
            "recaptcha" => $this->getRecaptchaSiteKey(),
            "password_min_length" => $this->passwordMinLength,
        ]);
    }

    /**
     * Expõe a chave publica do reCAPTCHA para as telas do fluxo de login.
     *
     * @return string|false
     */
    private function getRecaptchaSiteKey()
    {
        $recaptcha = Config::get("recaptcha");
        return !empty($recaptcha["auth"]) ? ($recaptcha["site_key"] ?? false) : false;
    }
}
