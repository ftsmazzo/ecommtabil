<?php
namespace App\Core;

use App\Core\Config;

class Scenery
{
    private const ALLOWED_CONTAINERS = ['container', 'container-fluid', 'container-extra'];

    private string $name;
    private array $config;
    private static ?Scenery $current = null;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->config = Config::get("app.sceneries.{$name}");
    }

    public function getMenu(int $userId, $router, $auth, $lang)
    {
        $menuService = $this->config['menu_service'];

        return (new $menuService(
            $router,
            $auth,
            $lang,
            new Cache()
        ))->generate($userId, $this->name);
    }

    public function getPreferences($userId)
    {
        $model = $this->config['preference_model'];
        $preferences = $model::find($userId, 'id_user');

        if ($preferences) {
            return $preferences;
        }

        return (object) [
            'id_user' => $userId,
            'tema' => 'light',
        ];
    }

    public function getCase(): string
    {
        return $this->config['case'] ?? 'normal';
    }

    public function getLayout($preferences): array
    {
        $themeIcons = [
            'light' => 'uil uil-moon',
            'dark'  => 'uil uil-sun',
        ];

        $layout = $this->config['layout'];
        $theme = $preferences->tema ?? 'light';
        $menuConfig = $layout['menu'] ?? [];
        if (!is_array($menuConfig)) {
            $menuConfig = ['type' => $menuConfig];
        }

        $topbarConfig = $layout['topbar'] ?? [];
        if (!is_array($topbarConfig)) {
            $topbarConfig = [];
        }

        $menu = $this->config['menu']
            ?? $menuConfig['type']
            ?? $layout['menu']
            ?? 'vertical';

        $layoutSize = $menu === 'horizontal'
            ? ($menuConfig['size'] ?? $layout['layout_size'] ?? 'default')
            : 'default';
        $layoutSize = in_array($layoutSize, ['default', 'compact'], true) ? $layoutSize : 'default';

        $layout['theme-icon']   = $themeIcons[$theme] ?? $themeIcons['light'];
        $layout['layout-color'] = $theme;
        $layout['layout-size']  = $layoutSize;
        $layout['menu'] = $menu;
        $menuIcons = $menuConfig['icons'] ?? $layout['menu_icons'] ?? true;
        $menuIcons = filter_var($menuIcons, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $menuIcons = $menuIcons ?? true;
        $menuContainer = $this->normalizeContainer(
            $menuConfig['container'] ?? $layout['menu_container'] ?? 'container-fluid',
            'container-fluid'
        );
        $topbarContainer = $this->normalizeContainer(
            $topbarConfig['container'] ?? $layout['topbar_container'] ?? 'container-fluid',
            'container-fluid'
        );
        $layout['menu_config'] = [
            'type' => $menu,
            'size' => $layoutSize,
            'icons' => $menuIcons,
            'container' => $menuContainer,
        ];
        $layout['menu-icons'] = $menuIcons;
        $layout['menu-container'] = $menuContainer;
        $layout['topbar-logo-height'] = (string) ($topbarConfig['logo_height'] ?? $layout['topbar-logo-height'] ?? '48');
        $layout['topbar-logo-height-sm'] = (string) ($topbarConfig['logo_height_sm'] ?? $layout['topbar-logo-height-sm'] ?? '20');
        $layout['topbar-logo-route'] = $topbarConfig['logo_route'] ?? $layout['topbar-logo-route'] ?? '#';
        $layout['topbar'] = [
            'logo_height' => $layout['topbar-logo-height'],
            'logo_height_sm' => $layout['topbar-logo-height-sm'],
            'logo_route' => $layout['topbar-logo-route'],
            'container' => $topbarContainer,
        ];
        $layout['topbar-container'] = $topbarContainer;
        $layout['content_container'] = $layout['content_container']
            ?? (($layout['menu'] ?? 'vertical') === 'horizontal' ? 'container-extra' : 'container-fluid');
        $layout['content_container'] = $this->normalizeContainer($layout['content_container'], 'container-fluid');
        $layout['sidebar_mobile_usercard'] = $layout['sidebar_mobile_usercard'] ?? true;

        return $layout;
    }

    private function normalizeContainer($value, string $default): string
    {
        return in_array($value, self::ALLOWED_CONTAINERS, true) ? $value : $default;
    }
}
