<?php

namespace App\Core;

use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

class Router
{
    private string $baseUrl;
    private string $namespace = "";
    private string $group = "";
    private array $middlewares = [];

    private array $routes = [];
    private array $namedRoutes = [];
    private $dispatcher;
    private $currentRouteError = null;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, "/");
    }

    public function group(?string $group, array $middlewares = []): self
    {
        // grupo raiz
        if ($group === null || trim($group) === "" || $group === "/") {
            $this->group = "";
        } else {
            $this->group = "/" . trim($group, "/");
        }

        $this->middlewares = $middlewares;
        return $this;
    }

    public function namespace(string $namespace): self
    {
        $this->namespace = trim($namespace, "\\");
        return $this;
    }

    private function add(string $method, string $route, $handler, ?string $name = null): void
    {
        $fullRoute = $this->group . "/" . ltrim($route, "/");
        $fullRoute = preg_replace("#/+#", "/", $fullRoute);

        if (is_string($handler) && str_contains($handler, ":")) {
            $handler = $this->namespace . "\\" . $handler;
        }

        $this->routes[] = [
            "method" => strtoupper($method),
            "route" => $fullRoute,
            "handler" => $handler,
            "name" => $name,
            "middlewares" => $this->middlewares,
        ];

        if ($name) {
            $this->namedRoutes[$name] = $fullRoute;
        }
    }

    public function get(string $route, $handler, ?string $name = null): void { $this->add("GET", $route, $handler, $name); }
    public function post(string $route, $handler, ?string $name = null): void { $this->add("POST", $route, $handler, $name); }
    public function put(string $route, $handler, ?string $name = null): void { $this->add("PUT", $route, $handler, $name); }
    public function patch(string $route, $handler, ?string $name = null): void { $this->add("PATCH", $route, $handler, $name); }
    public function delete(string $route, $handler, ?string $name = null): void { $this->add("DELETE", $route, $handler, $name); }

    public function route(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            return "#route-not-found";
        }

        $path = $this->namedRoutes[$name];

        // 1) substitui placeholders existentes no path
        if (preg_match_all('/\{([^}]+)\}/', $path, $m)) {
            $placeholders = $m[1]; // ex: ['id','slug']

            foreach ($placeholders as $key) {
                if (array_key_exists($key, $params) && $params[$key] !== null) {
                    $path = str_replace('{' . $key . '}', rawurlencode((string)$params[$key]), $path);
                    unset($params[$key]); // remove do query
                } else {
                    // se não veio valor, remove o placeholder (evita "{id}" na url)
                    $path = str_replace('{' . $key . '}', '', $path);
                }
            }
        }

        // limpa barras duplas que podem sobrar
        $path = preg_replace("#/+#", "/", $path);
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }

        // 2) o que sobrar vira query string
        // remove null/'' para não poluir
        $query = [];
        foreach ($params as $k => $v) {
            if ($v === null || $v === '') continue;
            $query[$k] = $v;
        }

        $qs = $query ? ('?' . http_build_query($query)) : '';

        return $this->baseUrl . $path . $qs;
    }

    public function hasNamedRoute(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    public function redirect(string $routeName, array $params = []): void
    {
        header("Location: " . $this->route($routeName, $params));
        exit;
    }

    private function runMiddlewares(array $middlewares, array $requestData): bool
    {
        foreach ($middlewares as $middleware) {
            if (!class_exists($middleware)) continue;

            $obj = new $middleware;

            if (!method_exists($obj, "handle")) continue;

            if ($obj->handle($requestData) === false) {
                return false;
            }
        }

        return true;
    }

    public function redirectBack(?string $fallbackRoute = null): void
    {
        if (!empty($_SERVER['HTTP_REFERER'])) {
            header("Location: {$_SERVER['HTTP_REFERER']}");
            exit;
        }

        if ($fallbackRoute) {
            $this->redirect($fallbackRoute);
        }

        header("Location: {$this->baseUrl}");
        exit;
    }

    public function back(?string $fallbackRoute = null): void
    {
        $this->redirectBack($fallbackRoute);
    }

    public function dispatch(): void
    {
        // 1) Dispatcher do FastRoute com payload (handler + middlewares + name)
        $this->dispatcher = simpleDispatcher(function (RouteCollector $r) {
            foreach ($this->routes as $rt) {

                $route = $rt["route"] ?? "/";

                // Normaliza rota cadastrada
                $route = preg_replace('#/+#', '/', $route);
                if ($route !== '/' && str_ends_with($route, '/')) {
                    $route = rtrim($route, '/');
                }

                // Payload para garantir middlewares corretos (inclusive em rotas dinâmicas)
                $payload = [
                    'handler'      => $rt["handler"],
                    'middlewares'  => $rt["middlewares"] ?? [],
                    'name'         => $rt["name"] ?? null,
                    'method'       => $rt["method"] ?? 'GET',
                    'route'        => $route,
                ];

                $r->addRoute($rt["method"], $route, $payload);
            }
        });

        $httpMethod   = $this->resolveHttpMethod();
        $uriOriginal  = $this->resolveRequestPath();
        $uri          = $this->normalizeUriForRouting($uriOriginal);

        $routeInfo = $this->dispatcher->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {

            case \FastRoute\Dispatcher::NOT_FOUND:
                $this->sandboxError(
                    "HTTP 404 - Rota não encontrada\nURI original: {$uriOriginal}\nURI resolvida: {$uri}\nMethod: {$httpMethod}",
                    404
                );
                return;

            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                $allowed = isset($routeInfo[1]) ? implode(', ', (array)$routeInfo[1]) : '';
                $this->sandboxError(
                    "HTTP 405 - Method Not Allowed\nURI original: {$uriOriginal}\nURI resolvida: {$uri}\nMethod: {$httpMethod}\nAllowed: {$allowed}",
                    405
                );
                return;

            case \FastRoute\Dispatcher::FOUND:

                /** @var array{handler:mixed,middlewares:array,name:?string,method:string,route:string} $payload */
                $payload = $routeInfo[1];
                $vars    = $routeInfo[2] ?? [];

                $handlerObj  = $payload['handler'];
                $middlewares = $payload['middlewares'] ?? [];

                // Dados unificados da request (GET + POST + vars)
                $requestData = array_merge($_GET ?? [], $_POST ?? [], $vars);
                unset($requestData['route']);

                // Middlewares
                if (!$this->runMiddlewares($middlewares, $requestData)) {
                    return;
                }

                // Closure
                if ($handlerObj instanceof \Closure) {
                    $handlerObj(...array_values($vars));
                    return;
                }

                // Controller:method
                if (is_string($handlerObj) && str_contains($handlerObj, ":")) {

                    [$controller, $action] = explode(":", $handlerObj, 2);

                    if (!class_exists($controller)) {
                        $this->sandboxError(
                            "Controller não encontrado: {$controller}\nURI original: {$uriOriginal}\nURI resolvida: {$uri}",
                            500
                        );
                        return;
                    }

                    $instance = new $controller();

                    if (!method_exists($instance, $action)) {
                        $this->sandboxError(
                            "Método '{$action}()' não encontrado em {$controller}\nURI original: {$uriOriginal}\nURI resolvida: {$uri}",
                            500
                        );
                        return;
                    }

                    // Request para injeção
                    $requestObj = new Request();
                    $requestObj->setData($_GET ?? []);
                    $requestObj->appendData($_POST ?? []);
                    $requestObj->appendData($vars);
                    $requestObj->appendData(["route" => $uri]);

                    try {
                        $ref = new \ReflectionMethod($controller, $action);
                    } catch (\ReflectionException $e) {
                        $this->sandboxError(
                            "Erro ao refletir {$controller}::{$action}()\n{$e->getMessage()}\nURI original: {$uriOriginal}\nURI resolvida: {$uri}",
                            500
                        );
                        return;
                    }

                    $args = [];

                    foreach ($ref->getParameters() as $param) {

                        $ptype = $param->getType();
                        $pname = $param->getName();

                        // Request tipado
                        if ($ptype instanceof \ReflectionNamedType
                            && !$ptype->isBuiltin()
                            && $ptype->getName() === Request::class) {
                            $args[] = $requestObj;
                            continue;
                        }

                        // array tipado
                        if ($ptype instanceof \ReflectionNamedType
                            && $ptype->isBuiltin()
                            && $ptype->getName() === 'array') {
                            $args[] = $requestData;
                            continue;
                        }

                        // heurística legado
                        if (in_array($pname, ['request', 'data', 'input'], true)) {
                            $args[] = $requestData;
                            continue;
                        }

                        // default
                        if ($param->isDefaultValueAvailable()) {
                            $args[] = $param->getDefaultValue();
                            continue;
                        }

                        $args[] = null;
                    }

                    $ref->invokeArgs($instance, $args);
                    return;
                }

                $this->sandboxError(
                    "Handler inválido para a rota\nURI original: {$uriOriginal}\nURI resolvida: {$uri}",
                    500
                );
                return;
        }
    }

    /**
     * Resolve o método HTTP:
     * - HEAD => GET (para roteamento)
     * - POST + _method => PUT/PATCH/DELETE
     */
    private function resolveHttpMethod(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            $override = $_POST['_method'] ?? ($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null);
            if (is_string($override) && $override !== '') {
                $override = strtoupper(trim($override));
                if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                    $method = $override;
                }
            }
        }

        if ($method === 'HEAD') {
            $method = 'GET';
        }

        return $method;
    }

    /**
     * Pega o path da request sem querystring, com fallback seguro.
     */
    private function resolveRequestPath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (!is_string($path) || $path === '') return '/';
        return $path;
    }

    /**
     * Normaliza URI para bater com rotas:
     * - remove // duplicadas
     * - remove trailing slash
     * - remove prefixo URL_PATH quando roda em subpasta (com boundary seguro)
     * - remove /index.php quando rewrite é inconsistente
     */
    private function normalizeUriForRouting(string $uri): string
    {
        $uri = preg_replace('#/+#', '/', $uri) ?: '/';

        // remove barra final
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        // remove base path (subpasta) com boundary seguro
        $basePath = '/' . trim(defined('URL_PATH') ? (string) URL_PATH : '', '/');
        if ($basePath !== '/' && $basePath !== '' &&
            ($uri === $basePath || str_starts_with($uri, $basePath . '/'))
        ) {
            $uri = substr($uri, strlen($basePath));
            if ($uri === '') $uri = '/';
        }

        // remove index.php se sobrou
        if ($uri === '/index.php') $uri = '/';
        if (str_starts_with($uri, '/index.php/')) {
            $uri = substr($uri, strlen('/index.php'));
            if ($uri === '') $uri = '/';
        }

        // garante formato final
        if ($uri === '') $uri = '/';
        if ($uri !== '/' && !str_starts_with($uri, '/')) $uri = '/' . $uri;

        return $uri;
    }

    public function error()
    {
        return $this->currentRouteError;
    }

    public function contextFromUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $segment = explode('/', trim($uri, '/'))[0] ?? '';
        return $segment ?: 'public';
    }

    private function sandboxError(string $message, int $code = 500): void
    {
        // Em DEV / SANDBOX / LOCAL → exceção explícita (Whoops pega)
        if (\App\Core\Config::get('app.environment') !== 'production') {
            throw new \Exception($message, $code);
        }

        // Em PRODUÇÃO → só define o erro
        $this->currentRouteError = $code;
    }
}
