<?php

namespace App\Services\Menu;

class MenuRenderer
{
    public function render(array $items, int $level = 1, string $mode = 'vertical'): string
    {
        if ($mode === 'horizontal') {
            return $this->renderHorizontal($items, $level);
        }

        return $this->renderVertical($items, $level);
    }

    private function renderVertical(array $items, int $level = 1): string
    {
        $html = '';

        foreach ($items as $item) {
            if (!empty($item['horizontal_only'])) {
                continue;
            }

            $type = $item['type'] ?? 'link';
            $menu = $item['menu'] ?? '';
            $display = $item['display'] ?? '';
            $icon = $item['icon'] ?? '';
            $alert = $item['alert'] ?? false;
            $extra = $item['extra'] ?? '';
            $route = $item['route'] ?? '#';
            $drop = $item['drop'] ?? [];

            if ($type === 'title') {
                $html .= '<li class="nav-item">';
                $html .= '<span class="nav-link sidebar-title text-uppercase small fw-bold opacity-75 px-0">'
                    . htmlspecialchars($display) .
                    '</span>';
                $html .= '</li>';
                continue;
            }

            if ($type === 'separator') {
                $html .= '<li class="nav-item my-2"><hr class="dropdown-divider opacity-50"></li>';
                continue;
            }

            if ($type === 'link' || $type === 'modal') {
                $html .= $this->renderLink(
                    $type,
                    $menu,
                    $display,
                    $icon,
                    $route,
                    $extra,
                    $alert,
                    $level
                );
                continue;
            }

            if ($type === 'drop') {
                $html .= $this->renderDrop(
                    $menu,
                    $display,
                    $icon,
                    $extra,
                    $alert,
                    $drop,
                    $level
                );
            }
        }

        return $html;
    }

    private function renderHorizontal(array $items, int $level = 1): string
    {
        $html = '';

        foreach ($items as $item) {
            if (!empty($item['vertical_only'])) {
                continue;
            }

            $type = $item['type'] ?? 'link';
            $menu = $item['menu'] ?? '';
            $display = $item['display'] ?? '';
            $icon = $item['icon'] ?? '';
            $alert = $item['alert'] ?? false;
            $extra = $item['extra'] ?? '';
            $route = $item['route'] ?? '#';
            $drop = $item['drop'] ?? [];

            if ($type === 'title') {
                if ($level > 1) {
                    $html .= '<li><h6 class="dropdown-header">' . htmlspecialchars($display) . '</h6></li>';
                }
                continue;
            }

            if ($type === 'separator') {
                $html .= '<li><hr class="dropdown-divider"></li>';
                continue;
            }

            if ($type === 'link' || $type === 'modal') {
                $html .= $this->renderHorizontalLink(
                    $type,
                    $menu,
                    $display,
                    $icon,
                    $route,
                    $extra,
                    $alert,
                    $level
                );
                continue;
            }

            if ($type === 'drop') {
                $html .= $this->renderHorizontalDrop(
                    $menu,
                    $display,
                    $icon,
                    $extra,
                    $alert,
                    $drop,
                    $level
                );
            }
        }

        return $html;
    }

    private function renderLink(
        string $type,
        string $menu,
        string $display,
        string $icon,
        string $route,
        string $extra,
        $alert,
        int $level
    ): string {
        $href = $type === 'modal' ? '#' : $route;
        $html = '<li class="nav-item">';

        $html .= '<a href="' . htmlspecialchars($href) . '"
                    class="nav-link'
                    . ($level === 1 ? '' : ' nav-link-submenu')
                    . '"
                    data-menu="' . htmlspecialchars($menu) . '" '
                    . $extra . ' ';

        if ($type === 'modal') {
            $html .= 'data-bs-toggle="modal" data-bs-target="' . htmlspecialchars($route) . '" ';
        }

        $html .= '>';

        if ($level === 1) {
            $html .= '<span class="sidebar-icon">';
            if (!empty($icon)) {
                $html .= '<i class="' . htmlspecialchars($icon) . '"></i>';
            }
            $html .= '</span>';
        }

        $html .= '<span class="sidebar-text">' . htmlspecialchars($display) . '</span>';
        $html .= $this->renderAlert($alert);
        $html .= '</a>';
        $html .= '</li>';

        return $html;
    }

    private function renderHorizontalLink(
        string $type,
        string $menu,
        string $display,
        string $icon,
        string $route,
        string $extra,
        $alert,
        int $level
    ): string {
        $href = $type === 'modal' ? '#' : $route;
        $linkClass = $level === 1 ? 'nav-link app-horizontal-link' : 'dropdown-item';
        $html = $level === 1 ? '<li class="nav-item">' : '<li>';

        $html .= '<a href="' . htmlspecialchars($href) . '" class="' . $linkClass . '" data-menu="'
            . htmlspecialchars($menu) . '" ' . $extra . ' ';

        if ($type === 'modal') {
            $html .= 'data-bs-toggle="modal" data-bs-target="' . htmlspecialchars($route) . '" ';
        }

        $html .= '>';

        if (!empty($icon) && $level === 1) {
            $html .= '<span class="sidebar-icon"><i class="' . htmlspecialchars($icon) . '"></i></span>';
        }

        $html .= '<span class="sidebar-text">' . htmlspecialchars($display) . '</span>';
        $html .= $this->renderAlert($alert);
        $html .= '</a></li>';

        return $html;
    }

    private function renderHorizontalDrop(
        string $menu,
        string $display,
        $icon,
        string $extra,
        $alert,
        array $drop,
        int $level
    ): string {
        $isRoot = $level === 1;
        $itemClass = $isRoot ? 'nav-item dropdown app-nav-dropdown' : 'dropdown-submenu';
        $linkClass = $isRoot
            ? 'nav-link dropdown-toggle app-horizontal-link'
            : 'dropdown-item d-flex justify-content-between align-items-center app-submenu-toggle';

        $html = '<li class="' . $itemClass . '">';
        $html .= '<a href="#" class="' . $linkClass . '" data-menu="' . htmlspecialchars($menu) . '" '
            . $extra;

        if ($isRoot) {
            $html .= ' data-bs-toggle="dropdown" data-bs-auto-close="outside"';
        } else {
            $html .= ' data-menu-subtoggle="true"';
        }

        $html .= ' aria-expanded="false">';

        if (!empty($icon) && $isRoot) {
            $html .= '<span class="sidebar-icon"><i class="' . htmlspecialchars((string) $icon) . '"></i></span>';
        }

        $html .= '<span class="sidebar-text">' . htmlspecialchars($display) . '</span>';
        $html .= $this->renderAlert($alert);
        if (!$isRoot) {
            $html .= '<span class="link-arrow">';
            $html .= '<svg class="icon icon-sm" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">';
            $html .= '<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>';
            $html .= '</svg>';
            $html .= '</span>';
        }
        $html .= '</a>';
        $html .= '<ul class="dropdown-menu app-menu-dropdown-menu">';
        $html .= $this->renderHorizontal($drop, $level + 1);
        $html .= '</ul></li>';

        return $html;
    }

    private function renderDrop(
        string $menu,
        string $display,
        $icon,
        string $extra,
        $alert,
        array $drop,
        int $level
    ): string {
        $submenuId = 'submenu-' . htmlspecialchars($menu);
        $html = '<li class="nav-item has-submenu level-' . $level . '">';

        $html .= '<a href="#' . $submenuId . '"
                    class="nav-link d-flex justify-content-between align-items-center"
                    data-bs-toggle="collapse"
                    data-bs-target="#' . $submenuId . '"
                    aria-expanded="false"
                    aria-controls="' . $submenuId . '"
                    data-menu="' . htmlspecialchars($menu) . '" '
                    . $extra . '>';

        $html .= '<span class="d-flex align-items-center">';

        if ($level === 1) {
            $html .= '<span class="sidebar-icon">';
            if (!empty($icon)) {
                $html .= '<i class="' . htmlspecialchars((string) $icon) . '"></i>';
            }
            $html .= '</span>';
        }

        $html .= '<span class="sidebar-text">' . htmlspecialchars($display) . '</span>';
        $html .= $this->renderAlert($alert);
        $html .= '</span>';

        $html .= '<span class="link-arrow">';
        $html .= '<svg class="icon icon-sm" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">';
        $html .= '<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>';
        $html .= '</svg>';
        $html .= '</span>';

        $html .= '</a>';
        $html .= '<div class="multi-level collapse" id="' . $submenuId . '" aria-expanded="false">';
        $html .= '<ul class="flex-column nav submenu-level submenu-level-' . $level . '">';
        $html .= $this->render($drop, $level + 1);
        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</li>';

        return $html;
    }

    private function renderAlert($alert): string
    {
        if (!$alert) {
            return '';
        }

        $alertText = is_array($alert) ? ($alert[0] ?? '') : $alert;
        $alertBg = is_array($alert) ? ($alert[1] ?? 'danger') : 'danger';
        $alertColor = is_array($alert) ? ($alert[2] ?? 'white') : 'white';
        $alertIcon = is_array($alert) ? (isset($alert[3]) ? '<i class="' . $alert[3] . '"></i>' : '') : '';

        return '<span class="badge badge-sm bg-' . htmlspecialchars($alertBg) .
            ' text-' . htmlspecialchars($alertColor) . ' ms-2">' .
            $alertIcon .
            htmlspecialchars($alertText) .
            '</span>';
    }
}
