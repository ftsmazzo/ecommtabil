<?php

namespace App\Core;

use App\Core\DB;
use App\Core\Connection;
use PDO;
use Exception;

class Bootstrap
{
    /**
     * Inicializa a aplicação
     */
    public static function init(string $rootPath): void
    {
        $rootPath = rtrim($rootPath, '/');

        // 1. Carrega variáveis de ambiente
        Env::load($rootPath . '/.env');

        // 2. Inicializa Config
        Config::init($rootPath . '/config');

        // 3. Define ambiente
        $env = Config::get('app.environment', 'production');
        self::setupEnvironment($env, $rootPath);

        // 4. Handler global de exceptions
        self::registerExceptionHandler($env);

        // 5. Define timezone
        date_default_timezone_set(Config::get('app.timezone', 'America/Sao_Paulo'));

        // 6. Define constantes de URL e PATH + globals runtime
        self::defineGlobals($rootPath);

        // 7. Define a conexão com o banco de dados
        self::initializeDatabase();

        // 8. Registra dependências globais (Container)
        self::registerCoreDependencies();

        // 9. Define a View
        self::loadViews();

        // 10. Define ícones do layout
        self::loadIcons();

        // 11. Carrega as rotas
        self::loadRoutes($rootPath);
    }

    private static function setupEnvironment(string $env, string $rootPath): void
    {
        $env = strtolower($env);

        if (in_array($env, ['local', 'dev', 'sandbox', 'development'], true)) {

            // error_reporting(E_ALL);
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
            ini_set('display_errors', '1');

            $whoops = new \Whoops\Run;
            $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
            $whoops->register();

            ini_set('log_errors', '1');
            ini_set('error_log', __DIR__ . '/../../storage/logs/errors/php_dev.log');

            $minify = $rootPath . '/app/Lib/Minify.php';
            if (file_exists($minify)) {
                require_once $minify;
            }

        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
            ini_set('error_log', __DIR__ . '/../../storage/logs/errors/php_errors.log');
        }
    }

    private static function registerExceptionHandler(string $env): void
    {
        $env = strtolower($env);

        set_exception_handler(function (\Throwable $e) use ($env) {

            // DEV/SANDBOX/LOCAL → explode (Whoops / PHP)
            if (in_array($env, ['local', 'dev', 'sandbox', 'development'], true)) {
                throw $e;
            }

            // PRODUÇCampo ObrigatórioO → fluxo centralizado
            ExceptionHandler::handle($e);
        });
    }

    private static function defineGlobals(string $rootPath): void
    {
        $configApp = Config::get('app') ?? [];

        $protocol = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        ) ? 'https' : 'http';

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $path = trim((string) ($configApp['path'] ?? ''), '/');

        if (!defined('PATH_ROOT')) {
            define('PATH_ROOT', $rootPath);
        }

        if (!defined('PATH_STORAGE')) {
            define('PATH_STORAGE', $rootPath . '/storage');
        }

        if (!defined('URL_PATH')) {
            define('URL_PATH', $path);
        }

        if (!defined('URL_BASE')) {
            define('URL_BASE', $protocol . '://' . $host . ($path ? '/' . $path : ''));
        }

        if (!defined('TITLE_SITE')) {
            define('TITLE_SITE', $configApp['name'] ?? 'Application');
        }

        // Estado/config em runtime
        App::set('config.app', $configApp);
        App::set('name', $configApp['name']);
        App::set('env', $configApp['environment'] ?? 'production');
        App::set('timezone', $configApp['timezone'] ?? 'UTC');

        // Globals (extras) vão para o registry
        $globals = $configApp['globals'] ?? [];
        if (is_array($globals)) {
            foreach ($globals as $k => $v) {
                App::set((string) $k, $v);
            }
        }
    }

    private static function loadIcons(): void
    {
        $icons = Config::get('app.icons', []);

        $fa = $icons['font_awesome_version'] ?? '6.0.0';
        $uni = $icons['font_unicons_version'] ?? '4.0.8';
        $ti = $icons['font_tabler'] ?? 'latest'; // latest
        $lucide = $icons['font_lucide'] ?? 'latest'; // latest

        if ($fa && !defined('URL_FONT_AWESOME')) {
            define('URL_FONT_AWESOME', "https://use.fontawesome.com/releases/v{$fa}/css/all.css");
        } else {
            define('URL_FONT_AWESOME', "");
        }

        if ($uni && !defined('URL_FONT_UNICONS')) {
            define('URL_FONT_UNICONS', "https://unicons.iconscout.com/release/v{$uni}/css/line.css");
        } else {
            define('URL_FONT_UNICONS', "");
        }

        if ($ti && !defined('URL_FONT_TABLER')) {
            define('URL_FONT_TABLER', "https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@{$ti}/dist/tabler-icons.min.css");
        } else {
            define('URL_FONT_TABLER', "");
        }

        if ($lucide && !defined('URL_FONT_LUCIDE')) {
            define('URL_FONT_LUCIDE', "https://unpkg.com/@lucide/font@{$lucide}/distlucide.css");
        } else {
            define('URL_FONT_LUCIDE', "");
        }
    }

    /**
     * Carrega a conexão com o Banco de Dados
     * e registra o executor do ORM
     */
    private static function initializeDatabase(): void
    {
        $cfg = Config::get('database');

        // 1. Carrega config e conexões
        Connection::loadConfig($cfg);

        DB::setExecutor(function (
            string $sql,
            array $bindings,
            array $context = []
        ) {
            // fetch pode ser null de propósito (write). Não use ??
            $fetch = array_key_exists('fetch', $context) ? $context['fetch'] : PDO::FETCH_OBJ;
            $class = array_key_exists('class', $context) ? $context['class'] : null;

            return Connection::run(
                $sql,
                $bindings,
                $context['connection'] ?? null,
                $fetch,
                $class,
                $context['return'] ?? 'bool'
            );
        });
    }

    /**
     * Registra todas as dependências básicas da aplicação.
     */
    private static function registerCoreDependencies(): void
    {
        // Router
        $router = new Router(URL_BASE);
        Container::set(Router::class, $router);

        // View
        $view = new View();

        // 🔥 dados globais disponíveis em TODOS os templates
        $view->addData([
            'router'   => $router,
            'URL_BASE' => URL_BASE,
            'URL_PATH' => URL_PATH,
        ]);

        Container::set(View::class, $view);

        // Outros serviços centrais
        Container::set(Message::class, new Message());
        Container::set(Session::class, new Session());
        Container::set(Cookie::class, new Cookie());
        Container::set(Csrf::class, new Csrf());
        Container::set(Cache::class, new Cache());
        Container::set(Log::class, new Log());
        Container::set(Cors::class, new Cors());
        Container::set(Auth::class, new Auth()); // sem parâmetro de guard
        Container::set(Config::class, new Config());
    }

    /**
     * Carregar Views
     *
     * @return void
     */
    private static function loadViews(): void
    {
        $view = new View();

        $view->addData(
            [
                'router' => Container::get(Router::class),
            ]
        );

        Container::set(View::class, $view);
    }

    /**
     * Carrega e inicializa as rotas
     */
    private static function loadRoutes(string $rootPath): void
    {
        $routesFile = $rootPath . '/routes/routes.php';

        if (!file_exists($routesFile)) {
            throw new Exception("Arquivo de rotas não encontrado em: {$routesFile}");
        }

        /** @var Router $router */
        $router = Container::get(Router::class);

        // 🔹 Carrega as rotas
        require_once $routesFile;

        // 🔹 Executa o dispatch
        $router->dispatch();

        // 🔹 Tratamento de erro
        if ($router->error()) {
            self::handleHttpError($router);
        }
    }

    private static function handleHttpError(Router $router): void
    {
        $env = strtolower((string) Config::get('app.environment'));

        // Em DEV/SANDBOX/LOCAL → Whoops assume automaticamente
        if (in_array($env, ['local', 'dev', 'sandbox', 'development'], true)) {
            return;
        }

        $err = $router->error();
        http_response_code($err);

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $segment = explode('/', trim($uri, '/'))[0] ?? '';

        /**
         * 1️⃣ API → sempre JSON
         */
        if ($segment === 'api') {
            echo Json::encode([
                'error' => true,
                'code'  => $err
            ]);
            exit;
        }

        /**
         * 2️⃣ 404 amigável (prioridade máxima)
         */
        if ($err === 404) {
            // (new \App\Controllers\Web\PaginaController())->notFound();
            // exit;

            // se quiser redirecionar
            // $router->redirect('web.pagina.naoencontrada');
            // exit;
        }

        /**
         * 3️⃣ erro por contexto (admin.error, cliente.error…)
         */
        if ($segment) {
            $contextErrorRoute = "{$segment}.error";

            if ($router->hasNamedRoute($contextErrorRoute)) {
                $router->redirect($contextErrorRoute, ['code' => $err]);
                exit;
            }
        }

        /**
         * 4️⃣ erro web genérico
         */
        if ($router->hasNamedRoute('web.error')) {
            $router->redirect('web.error', ['code' => $err]);
            exit;
        }

        /**
         * 5️⃣ fallback final
         */
        echo "Erro {$err}";
        exit;
    }
}
