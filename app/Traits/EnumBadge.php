<?php

namespace App\Traits;

/**
 * Trait EnumBadgeTrait
 *
 * Fornece métodos utilitários para geração de badges HTML
 * baseados no label() e class() definidos no enum.
 *
 * Requer que o enum implemente:
 *  - public function label(): string
 *  - public function class(): string
 *
 * Funcionalidades:
 *  - badge() → retorna a badge HTML daquele case específico
 *  - badges() → retorna um array [value => badge HTML]
 *
 * Compatível com EnumHelpers::map()
 */
trait EnumBadge
{
    /**
     * Retorna a badge HTML da instancia atual do enum.
     *
     * @return string
     */
    public function badge(): string
    {
        return "<span class='badge bg-{$this->class()}'>{$this->label()}</span>";
    }

    /**
     * Retorna um array com todas as badges do enum.
     * Exemplo:
     * [
     *     "ATIVA" => "<span class='badge bg-success'>ATIVA</span>",
     *     "INATIVA" => "<span class='badge bg-danger'>INATIVA</span>",
     * ]
     *
     * @return array
     */
    public static function badges(): array
    {
        return self::map(fn($case) => $case->badge());
    }
}
