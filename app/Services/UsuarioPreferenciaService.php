<?php

namespace App\Services;

use App\Core\Cache;
use App\Models\UsuarioPreferencia;

class UsuarioPreferenciaService
{
    public function __construct(private Cache $cache = new Cache()) {}

    private function cacheKey(int $usuarioId): string
    {
        return 'preferences_usuario_' . $usuarioId;
    }

    private function limparCache(int $usuarioId): void
    {
        $this->cache->clear($this->cacheKey($usuarioId));
    }

    public function obter(int $usuarioId): ?UsuarioPreferencia
    {
        $key = $this->cacheKey($usuarioId);

        return $this->cache->remember($key, fn () => UsuarioPreferencia::porUsuario($usuarioId));
    }

    public function atualizar(int $usuarioId, array $dados): void
    {
        $prefs = UsuarioPreferencia::porUsuario($usuarioId);

        if ($prefs) {
            UsuarioPreferencia::where('id_user', '=', $usuarioId)->update($dados);
        } else {
            UsuarioPreferencia::insert(array_merge($dados, ['id_user' => $usuarioId]));
        }

        $this->limparCache($usuarioId);
    }

    public function alterarTema(int $usuarioId, string $tema): void
    {
        $this->atualizar($usuarioId, ['tema' => $tema]);
    }

}
