<?php
namespace App\Core;

// use CoffeeCode\Router\Router;
use App\Core\Router;

abstract class Controller
{
    /** @var Router */
    protected Router $router;

    /** @var View */
    protected View $view;

    /** @var Message */
    protected Message $message;

    /** @var Session */
    protected Session $session;

    /** @var Cookie */
    protected Cookie $cookie;

    /** @var Csrf */
    protected Csrf $csrf;

    /** @var Cache */
    protected Cache $cache;

    /** @var Log */
    protected Log $log;

    /** @var Cors */
    protected Cors $cors;

    /** @var Auth */
    protected Auth $auth;

    /** @var array */
    protected array $authconfig;

    /** @var array|null */
    protected ?array $lang = null;

    public function __construct()
    {
        // 🔹 Recupera todas as dependências centrais do Container
        $this->router  = Container::get(Router::class);
        $this->view    = Container::get(View::class);
        $this->message = Container::get(Message::class);
        $this->session = Container::get(Session::class);
        $this->cookie  = Container::get(Cookie::class);
        $this->csrf    = Container::get(Csrf::class);
        $this->cache   = Container::get(Cache::class);
        $this->log     = Container::get(Log::class);
        $this->cors    = Container::get(Cors::class);
        $this->auth    = Container::get(Auth::class);

        // 🔹 Configurações de autenticação globais
        $this->authconfig = Config::get('auth');
    }

    // --------------------------------------------------
    // 🧩 Métodos utilitários
    // --------------------------------------------------

    protected function _t(string $index, ?string $alt = null): string
    {
        return $this->lang[$index] ?? $alt ?? $index;
    }

    protected function ajaxResponse(string $param, array $values): string
    {
        return json_encode([$param => $values]);
    }

    protected function loggedApi(): void
    {
        if (isset($this->auth) && $this->auth->auth() === false) {
            unauthorized();
        }
    }
}
