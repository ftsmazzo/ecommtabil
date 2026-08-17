<?php
namespace App\Core;

use App\Core\Scenery;
use App\Core\Config;

abstract class ControllerCliente extends Controller
{

    protected $user;
    protected Scenery $scenery;

    public function __construct()
    {
        parent::__construct();

        // Configura o guard de autenticação
        $this->auth->setGuard('user');

        // VERIFICAÇÃO DE USUÁRIO LOGADO
        if (!$this->auth->auth()) {
            $this->message->flash(6);
            $this->router->redirect($this->auth->getRouteLogin());
        }

        $this->user = $this->auth->user();

        $this->scenery = new Scenery('cliente');

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
                'logo_login'   => storage() . '/layout/' . ($layout['logo_login'] ?? $layout['logo_light']),
                'logo_sidebar' => storage() . '/layout/' . ($layout['logo_sidebar'] ?? $layout['logo_light']),
                'logo_mobile'  => storage() . '/layout/' . ($layout['logo_mobile'] ?? $layout['logo_sidebar'] ?? $layout['logo_light']),
                'logo_light'   => storage() . '/layout/' . $layout['logo_light'],
                'logo_dark'    => storage() . '/layout/' . $layout['logo_dark'],
                'push_public_key' => Config::get('push.publicKey'),
            ]
        );
    }

    public function logged(): void
    {
        // Método vazio para ser sobrescrito se necessário
    }

    protected function authorize(string $permission, ?string $redirect = null): void
    {
        if ($this->auth->allow($permission)) {
            return;
        }

        $this->message->flash(7);

        if ($redirect) {
            $this->router->redirect($redirect);
        } else {
            Redirect::referer();
        }

        exit;
    }


}
