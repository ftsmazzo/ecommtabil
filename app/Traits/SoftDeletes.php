<?php

namespace App\Traits;

use RuntimeException;

trait SoftDeletes
{
    protected static string $deletedColumn = 'deleted_at';

    /**
     * Aplica o filtro de soft delete automaticamente.
     */
    protected static function applySoftDeleteScope($query)
    {
        return $query->whereNull(static::$deletedColumn);
    }

    /**
     * Remove logicamente o registro.
     */
    public function softDelete(): bool
    {
        if (!isset($this->data->{static::$primary})) {
            throw new RuntimeException('Model não persistido');
        }

        return static::query()
            ->where(static::$primary, '=', $this->data->{static::$primary})
            ->update([static::$deletedColumn => date('Y-m-d H:i:s')]) > 0;
    }

    /**
     * Restaura um registro removido logicamente.
     */
    public function restore(): bool
    {
        if (!isset($this->data->{static::$primary})) {
            throw new RuntimeException('Model não persistido');
        }

        return static::withTrashed()
            ->where(static::$primary, '=', $this->data->{static::$primary})
            ->update([static::$deletedColumn => null]) > 0;
    }

    /**
     * Retorna query sem filtro de soft delete.
     */
    public static function withTrashed()
    {
        return \App\Core\DB::table(
            static::$table . (static::$alias ? ' AS ' . static::$alias : '')
        );
    }

    /**
     * Retorna apenas registros deletados.
     */
    public static function onlyTrashed()
    {
        return static::withTrashed()
            ->whereNotNull(static::$deletedColumn);
    }
}
