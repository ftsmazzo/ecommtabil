<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Data;
use App\Core\DB;
use App\Core\Log;
use App\Core\Message;
use App\Core\Recaptcha;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Services\AuthHistoricoService;

class LoginService
{
    public function __construct(
        private Auth $auth,
        private Router $router,
        private Message $message,
        private Session $session,
        private Log $log,
    ) {
    }

    public function makeInputConfig(array $labels = []): array
    {
        return [
            "user" => [
                "type" => $this->auth->getTypeLogin(),
                "label" => $labels["user_label"] ?? "Usuário",
                "placeholder" => $labels["user_placeholder"] ?? "Digite seu usuário",
            ],
            "pass" => [
                "label" => $labels["pass_label"] ?? "Senha",
                "placeholder" => $labels["pass_placeholder"] ?? "Digite sua senha",
            ],
        ];
    }

    public function authenticate(Request $request, array $options = []): void
    {
        $data = new Data($request->all());
        $historyClass = $options["history_class"] ?? AuthHistoricoService::class;
        $historyTable = $options["history_table"] ?? null;
        $loginRoute = $options["login_route"] ?? $this->auth->getRouteLogin();
        $animateError = $options["animate_error"] ?? "animate__shakeX";
        $rememberField = $options["remember_field"] ?? "remember";
        $authGuard = $options["auth_guard"] ?? $this->auth->getGuard();
        $onSuccess = $options["on_success"] ?? null;

        if (!$data->has("login") || !$data->has("senha")) {
            $fieldsEmpty = $data->has("login") ? "Senha" : "Login";

            if ($historyTable) {
                $historyClass::registrar(
                    $historyTable,
                    [
                        "login" => $data->login,
                        "acao" => "login",
                        "status" => "error",
                        "motivo" => "{$fieldsEmpty} vazio",
                    ]
                );
            }

            $this->message->flash("Informe seu usuário e senha para entrar", "danger", null, 'alert');
            $this->session->set("animate", $animateError);
            $this->session->set("login_error", true);
            $this->router->redirect($loginRoute);
        }

        if (!$data->blackListed(["login", "senha"])) {
            if ($historyTable) {
                $historyClass::registrar(
                    $historyTable,
                    [
                        "login" => $data->login,
                        "acao" => "login",
                        "status" => "error",
                        "motivo" => "Input bloqueado (blacklist)",
                    ]
                );
            }

            $this->message->flash("Ocorreu um erro ao tentar entrar", "danger", true, 'alert');
            $this->session->set("animate", $animateError);
            $this->router->redirect($loginRoute);
        }

        $ip = getip();
        $ua = $_SERVER["HTTP_USER_AGENT"] ?? "unknown";
        $data->zeroIfEmpty($rememberField);

        if ($authGuard) {
            $this->auth->setGuard($authGuard);
        }

        if (!$this->validateRecaptcha($data->recaptcha ?? null)) {
            if ($historyTable) {
                $historyClass::registrar(
                    $historyTable,
                    [
                        "login" => $data->login,
                        "acao" => "login",
                        "status" => "error",
                        "motivo" => "reCAPTCHA invalido",
                    ]
                );
            }

            $this->message->flash("Não foi possível validar a tentativa de login. Tente novamente.", "danger", true);
            $this->session->set("animate", $animateError);
            $this->router->redirect($loginRoute);
        }

        $signin = $this->auth->signIn($data->login, $data->senha, $data->{$rememberField});

        if ($signin["error"]) {
            $this->session->set("animate", $animateError);
            $this->message->flash($signin["message"], "danger", true);
            $this->router->redirect($loginRoute);
        }

        $user = $this->auth->user();

        $this->ensureUserToken($user);

        if (is_callable($onSuccess)) {
            $onSuccess($user, $data, $ip, $ua, $signin);
        }

        $this->log->app("Login efetuado", ["user" => $user->uid, "ip" => $ip, "ua" => $ua]);

        if ($historyTable) {
            $historyClass::registrar(
                $historyTable,
                [
                    "id_usuario" => $user->uid,
                    "login" => $user->{$this->auth->getColumnLogin()},
                    "acao" => "login",
                    "status" => "success",
                    "motivo" => "Acesso autorizado",
                ]
            );
        }

        if ($this->session->has("url_redirect")) {
            Redirect::go($this->session->url_redirect);
            $this->session->unset("url_redirect");
        }

        $this->router->redirect($this->auth->getRouteRedirect());
    }

    /**
     * Valida o reCAPTCHA no backend.
     *
     * O front-end continua gerando o token, mas a decisão final precisa ser do
     * servidor para evitar bypass por requisições manuais.
     *
     * @param string|null $token
     * @param float|null $score
     * @return bool
     */
    public function validateRecaptcha(?string $token, ?float $score = null): bool
    {
        $config = Config::get("recaptcha");

        if (empty($config["auth"])) {
            return true;
        }

        $token = trim((string)$token);
        if ($token === "") {
            return false;
        }

        try {
            $recaptcha = new Recaptcha();
            $recaptcha->setIp(getip());

            $valid = $recaptcha->validate($token, $score);

            if (!$valid) {
                $this->log->app("reCAPTCHA inválido", [
                    "guard" => $this->auth->getGuard(),
                    "ip" => getip(),
                    "error_codes" => $recaptcha->getLastError(),
                ]);
            }

            return $valid;
        } catch (\Throwable $e) {
            $this->log->app("Falha ao validar reCAPTCHA", [
                "guard" => $this->auth->getGuard(),
                "ip" => getip(),
                "error" => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function ensureUserToken(object $user): void
    {
        $tokenColumn = $this->auth->getColumnToken();

        if (!$tokenColumn || !empty($user->token)) {
            return;
        }

        $newToken = substr(hash("sha256", microtime(true) . random_int(1, PHP_INT_MAX)), 0, 64);

        DB::table($this->auth->getTable())
            ->where($this->auth->getColumnId(), "=", $user->uid)
            ->update([$tokenColumn => $newToken]);

        $user->token = $newToken;
    }
}
