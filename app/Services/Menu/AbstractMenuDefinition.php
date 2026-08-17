<?php

namespace App\Services\Menu;

abstract class AbstractMenuDefinition
{
    abstract public function items(): array;

    protected function link(
        string $menu,
        string $display,
        ?string $routeName = null,
        array $extra = []
    ): array {
        return array_merge([
            'type' => 'link',
            'menu' => $menu,
            'display' => $display,
            'route_name' => $routeName,
            'icon' => '',
            'alert' => false,
            'extra' => '',
        ], $extra);
    }

    protected function modal(string $menu, string $display, string $target, array $extra = []): array
    {
        return array_merge([
            'type' => 'modal',
            'menu' => $menu,
            'display' => $display,
            'target' => $target,
            'icon' => '',
            'alert' => false,
            'extra' => '',
        ], $extra);
    }

    protected function drop(string $menu, string $display, array $children, array $extra = []): array
    {
        return array_merge([
            'type' => 'drop',
            'menu' => $menu,
            'display' => $display,
            'children' => $children,
            'icon' => false,
            'alert' => false,
            'extra' => '',
        ], $extra);
    }

    protected function title(string $display, array $extra = []): array
    {
        return array_merge([
            'type' => 'title',
            'display' => $display,
        ], $extra);
    }

    protected function separator(array $extra = []): array
    {
        return array_merge([
            'type' => 'separator',
        ], $extra);
    }

    protected function badge(
        string $label,
        string $bg = 'danger',
        string $color = 'white',
        string $icon = ''
    ): array {
        return [
            'label' => $label,
            'bg' => $bg,
            'color' => $color,
            'icon' => $icon,
        ];
    }
}
