<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Router;
use App\Models\UsuarioProjetoRecente;
use App\Services\Menu\AdminMenuDefinition;
use App\Services\Menu\ClienteMenuDefinition;
use App\Services\Menu\MenuBuilder;

class MenuService
{
    public const RECENT_PROJECTS_CACHE_PREFIX = "menu_admin_usuario_";

    protected Router $router;
    protected Auth $auth;
    protected ?array $lang;
    protected Cache $cache;

    private array $sceneries = [
        'admin' => AdminMenuDefinition::class,
        'cliente' => ClienteMenuDefinition::class,
    ];

    public function __construct(Router $router, Auth $auth, ?array $lang, Cache $cache)
    {
        $this->router = $router;
        $this->auth = $auth;
        $this->lang = $lang;
        $this->cache = $cache;
    }

    public function generate(int $userId, string $scenery = 'admin'): array
    {
        $guard = $this->auth->getGuard();
        $cacheKey = "menu_{$scenery}_{$guard}_{$userId}";

        $menu = $this->cache->remember($cacheKey, function () use ($scenery) {
            if (!isset($this->sceneries[$scenery])) {
                return [];
            }

            return $this->buildSceneryMenu($scenery);
        });

        return $this->injectDynamicItems($menu, $scenery, $userId);
    }

    private function buildSceneryMenu(string $scenery): array
    {
        $definitionClass = $this->sceneries[$scenery] ?? null;

        if ($definitionClass === null) {
            return [];
        }

        $builder = new MenuBuilder($this->router);
        $definition = $this->makeDefinition($definitionClass);

        return $builder->build($definition->items(), $this->permissions());
    }

    private function makeDefinition(string $definitionClass): object
    {
        if ($definitionClass === AdminMenuDefinition::class) {
            return new $definitionClass($this->router, $this->auth);
        }

        return new $definitionClass();
    }

    private function permissions(): array
    {
        return (array) $this->auth->permissions();
    }

    private function injectDynamicItems(array $menu, string $scenery, int $userId): array
    {
        if ($scenery !== 'admin') {
            return $menu;
        }

        foreach ($menu as &$item) {
            if (($item['menu'] ?? null) === 'projetos') {
                $item['type'] = 'drop';
                $item['route'] = '#';
                $item['drop'] = $this->buildAdminProjectDropdown($userId);
                $item['icon'] = $item['icon'] ?? 'uil uil-chart-growth';
                unset($item['route_name']);
                break;
            }
        }

        unset($item);

        return $menu;
    }

    private function buildAdminProjectDropdown(int $userId): array
    {
        $items = [[
            'type' => 'link',
            'menu' => 'projetos-todos',
            'display' => 'Todos os Projetos',
            'route' => $this->router->route('admin.projeto.index'),
            'icon' => '',
            'drop' => false,
            'alert' => false,
            'extra' => '',
        ]];

        $recentes = $this->recentProjects($userId);

        if ($recentes !== []) {
            $items[] = ['type' => 'separator'];

            foreach ($recentes as $projeto) {
                $items[] = [
                    'type' => 'link',
                    'menu' => 'projetos-recente-' . (int) ($projeto['id'] ?? 0),
                    'display' => (string) ($projeto['label'] ?? 'Projeto'),
                    'route' => $this->router->route('admin.projeto.abrir', ['id' => (int) ($projeto['id'] ?? 0)]),
                    'icon' => '',
                    'drop' => false,
                    'alert' => false,
                    'extra' => '',
                ];
            }
        }

        return $items;
    }

    private function recentProjects(int $userId): array
    {
        $cacheKey = self::recentProjectsCacheKey($userId);

        return $this->cache->remember($cacheKey, function () use ($userId) {
            return UsuarioProjetoRecente::recentesPorUsuario($userId, 10);
        });
    }

    public static function recentProjectsCacheKey(int $userId): string
    {
        return self::RECENT_PROJECTS_CACHE_PREFIX . $userId . "_projetos_recentes";
    }
}
