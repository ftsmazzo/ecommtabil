<?php

namespace App\Services\Menu;

use App\Core\Router;

class MenuBuilder
{
    public function __construct(private Router $router)
    {
    }

    public function build(array $definition, array $permissions): array
    {
        $items = [];

        foreach ($definition as $item) {
            $prepared = $this->prepareItem($item, $permissions);

            if ($prepared !== null) {
                $items[] = $prepared;
            }
        }

        return $this->cleanupTitles($items);
    }

    private function prepareItem(array $item, array $permissions): ?array
    {
        if (!$this->isEnabled($item) || !$this->isAllowed($item, $permissions)) {
            return null;
        }

        $type = $item['type'] ?? 'link';

        if ($type === 'title') {
            return [
                'type' => 'title',
                'display' => $item['display'],
            ];
        }

        if ($type === 'separator') {
            return [
                'type' => 'separator',
            ];
        }

        $modeFlags = array_filter([
            'vertical_only'   => $item['vertical_only']   ?? false,
            'horizontal_only' => $item['horizontal_only'] ?? false,
        ]);

        if ($type === 'drop') {
            $children = [];

            foreach ($item['children'] ?? [] as $child) {
                $preparedChild = $this->prepareItem($child, $permissions);

                if ($preparedChild !== null) {
                    $children[] = $preparedChild;
                }
            }

            $children = $this->cleanupTitles($children);

            if (empty($children)) {
                return null;
            }

            return $modeFlags + [
                'type' => 'drop',
                'menu' => $item['menu'],
                'display' => $item['display'],
                'icon' => $item['icon'] ?? false,
                'drop' => $children,
                'alert' => $this->normalizeAlert($this->resolveValue($item['alert'] ?? false)),
                'extra' => (string) $this->resolveValue($item['extra'] ?? ''),
            ];
        }

        if ($type === 'modal') {
            return $modeFlags + [
                'type' => 'modal',
                'menu' => $item['menu'],
                'display' => $item['display'],
                'icon' => $item['icon'] ?? '',
                'route' => $item['target'] ?? '#',
                'drop' => false,
                'alert' => $this->normalizeAlert($this->resolveValue($item['alert'] ?? false)),
                'extra' => (string) $this->resolveValue($item['extra'] ?? ''),
            ];
        }

        return $modeFlags + [
            'type' => 'link',
            'menu' => $item['menu'],
            'display' => $item['display'],
            'route' => $this->resolveRoute($item),
            'icon' => $item['icon'] ?? '',
            'drop' => false,
            'alert' => $this->normalizeAlert($this->resolveValue($item['alert'] ?? false)),
            'extra' => (string) $this->resolveValue($item['extra'] ?? ''),
        ];
    }

    private function cleanupTitles(array $items): array
    {
        $cleaned = [];
        $pendingTitle = null;

        foreach ($items as $item) {
            $type = $item['type'] ?? 'link';

            if ($type === 'title') {
                $pendingTitle = $item;
                continue;
            }

            if ($pendingTitle !== null) {
                $cleaned[] = $pendingTitle;
                $pendingTitle = null;
            }

            $cleaned[] = $item;
        }

        return array_values($cleaned);
    }

    private function isAllowed(array $item, array $permissions): bool
    {
        $required = array_values(array_filter((array) ($item['permission'] ?? [])));

        if (empty($required)) {
            return true;
        }

        foreach ($required as $permission) {
            if (in_array($permission, $permissions, true)) {
                return true;
            }
        }

        return false;
    }

    private function isEnabled(array $item): bool
    {
        return !array_key_exists('enabled', $item) || $item['enabled'] !== false;
    }

    private function resolveRoute(array $item): string
    {
        if (isset($item['route'])) {
            return (string) $this->resolveValue($item['route']);
        }

        $routeName = $item['route_name'] ?? null;

        if (empty($routeName)) {
            return '#';
        }

        return $this->router->route($routeName, $item['route_params'] ?? []);
    }

    private function resolveValue($value)
    {
        return is_callable($value) ? $value() : $value;
    }

    private function normalizeAlert($alert)
    {
        if (!$alert || !is_array($alert)) {
            return $alert;
        }

        if (array_key_exists('label', $alert)) {
            return [
                $alert['label'] ?? '',
                $alert['bg'] ?? 'danger',
                $alert['color'] ?? 'white',
                $alert['icon'] ?? '',
            ];
        }

        return $alert;
    }
}
