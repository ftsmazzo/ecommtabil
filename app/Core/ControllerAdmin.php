<?php
namespace App\Core;

use App\Core\Scenery;
use App\Core\Config;

abstract class ControllerAdmin extends Controller
{

    protected $user;
    protected Scenery $scenery;

    public function __construct()
    {
        parent::__construct();

        // Configura o guard de autenticação
        $this->auth->setGuard('usuario');

        // VERIFICAÇÃO DE USUÁRIO LOGADO
        if (!$this->auth->auth()) {
            $this->message->flash(6);
            $loginUrl = $this->router->route($this->auth->getRouteLogin());

            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

            if ($isAjax) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                header('X-Auth-Redirect: ' . $loginUrl);
                echo json_encode(['error' => true, 'auth' => false, 'redirect' => $loginUrl], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $this->router->redirect($this->auth->getRouteLogin());
        }

        $this->user = $this->auth->user();

        $this->scenery = new Scenery('admin');

        $menu = $this->scenery->getMenu(
            $this->user->uid,
            $this->router,
            $this->auth,
            $this->lang
        );

        $preferenceCacheName = 'preferences_' . $this->auth->getGuard() . '_' . $this->user->uid;

        $preferences = $this->cache->remember($preferenceCacheName, $this->scenery->getPreferences($this->user->uid));

        $layout = $this->scenery->getLayout($preferences);
        $layout["topbar-logo-route"] = $this->router->route($layout["topbar-logo-route"]);

        // LOGOS
        $logoLogin = $layout['logo_login'] ?? $layout['logo_sidebar'] ?? $layout['logo_light'] ?? null;
        $logoSidebar = $layout['logo_sidebar'] ?? $layout['logo_light'] ?? null;
        $logoMobile = $layout['logo_mobile'] ?? $layout['logo_sidebar'] ?? $layout['logo_light'] ?? null;
        $logoLight = $layout['logo_light'] ?? null;
        $logoDark = $layout['logo_dark'] ?? null;

        $this->view->addData(
            [
                'auth'         => $this->auth,
                'user'         => $this->user,
                'menu'         => $menu,
                'notificacao'  => [],
                'message'      => $this->message,
                'session'      => $this->session,
                'title_site'   => TITLE_SITE,
                'layout'       => $layout,
                'preferencias' => $preferences,
                'logo_login'   => layout($logoLogin),
                'logo_sidebar' => layout($logoSidebar),
                'logo_mobile'  => layout($logoMobile),
                'logo_light'   => layout($logoLight),
                'logo_dark'    => layout($logoDark),
                'push_public_key' => Config::get('push.publicKey'),
            ]
        );
    }

    public function logged(): void
    {
        // Método vazio para ser sobrescrito se necessário
    }

    protected function authorize(string $permission, string|callable|null $onFail = null): void
    {
        if ($this->auth->allow($permission)) {
            return;
        }

        // Se for uma função, executa e encerra
        if (is_callable($onFail)) {
            $onFail(); // o callback decide o que fazer (json, etc)
            exit;
        }

        $this->message->flash(7);

        // Se for string, trata como redirect
        if (is_string($onFail) && $onFail !== '') {
            $this->router->redirect($onFail);
            exit;
        }

        // Padrão antigo
        Redirect::referer();
        exit;
    }


}
