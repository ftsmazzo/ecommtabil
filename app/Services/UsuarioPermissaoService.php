<?php

namespace App\Services;

use App\Core\Config;
use App\Models\UsuarioPermissao;

class UsuarioPermissaoService
{
    /**
     * Retorna a matriz de permissões agrupada para renderização nos formulários.
     *
     * @param array $selectedIds
     * @return array
     */
    public static function grouped(array $selectedIds = []): array
    {
        $grouped = [];
        $selectedMap = array_fill_keys(array_map('intval', $selectedIds), true);
        $hideRamo = !((bool) Config::get('modulos.cliente.use_ramo', false));

        $agrupamentos = UsuarioPermissao::select("DISTINCT(agrupamento) as agrupamento")
            ->orderBy("agrupamento")
            ->get();

        foreach ($agrupamentos as $agrupamento) {
            $grupos = UsuarioPermissao::select("DISTINCT(grupo)")
                ->where("agrupamento", "=", $agrupamento->agrupamento)
                ->orderBy("grupo")
                ->pluck("grupo");

            foreach ($grupos as $grupo) {
                $permissions = UsuarioPermissao::where("grupo", "=", $grupo)
                    ->select("id", "descricao", "permissao")
                    ->orderBy("descricao")
                    ->get();

                if ($hideRamo) {
                    $permissions = array_values(array_filter($permissions, function ($permission) {
                        return !str_starts_with((string) $permission->permissao, 'cliente_ramo_');
                    }));
                }

                if (empty($permissions)) {
                    continue;
                }

                foreach ($permissions as $permission) {
                    $permissionId = (int) $permission->id;
                    $permission->check = isset($selectedMap[$permissionId]);
                }

                $grouped[$agrupamento->agrupamento][$grupo] = $permissions;
            }
        }

        return $grouped;
    }

    /**
     * Normaliza o payload de permissões salvo em texto/json para ids inteiros.
     *
     * @param mixed $value
     * @return array
     */
    public static function selectedIds($value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = json_decode((string) $value, true);

            if (is_string($items)) {
                $items = json_decode($items, true);
            }
        }

        if (!is_array($items)) {
            return [];
        }

        $items = array_map('intval', $items);
        $items = array_values(array_unique(array_filter($items, fn ($item) => $item > 0)));

        return $items;
    }
}
