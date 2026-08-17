<?php

namespace App\Traits;

/**
 * Trait EnumHelpers
 *
 * Fornece funcionalidades genéricas e reutilizáveis para Enums,
 * incluindo cache interno para melhorar performance.
 *
 * Esta trait deve ser usada em qualquer Enum que necessite:
 *
 * - values()    → lista apenas dos valores (strings)
 * - casesMap()  → value => case (lookup super rápido)
 * - map()       → mapeamento customizado, com cache interno
 *
 * Observação:
 * ------------
 * O cache usa variáveis estáticas, garantindo que o resultado seja
 * gerado apenas uma única vez por request, reduzindo drasticamente
 * o custo em loops com milhares de registros.
 */
trait EnumHelpers
{
    /**
     * Retorna apenas os valores do enum.
     *
     * @return array Lista dos valores (strings)
     */
    public static function values(): array
    {
        return array_map(
            fn($case) => $case->value,
            self::cases()
        );
    }

    /**
     * Retorna um mapa value => case, ideal para consulta rápida.
     *
     * @return array
     */
    public static function casesMap(): array
    {
        static $cache = null;

        if ($cache === null) {
            $cache = [];

            foreach (self::cases() as $case) {
                $cache[$case->value] = $case;
            }
        }

        return $cache;
    }

    /**
     * Permite criar qualquer mapa (value => algo) baseado em um callback.
     *
     * Exemplo:
     * EmpresaSituacao::map(fn($case) => $case->class());
     *
     * @param callable $callback Função que recebe o case e retorna o valor.
     * @return array Mapa de value => retorno do callback
     */
    public static function map(callable $callback): array
    {
        static $cache = [];

        // id único para esse callback
        $key = spl_object_id((object) $callback);

        if (!isset($cache[$key])) {

            $cache[$key] = [];

            foreach (self::cases() as $case) {
                $cache[$key][$case->value] = $callback($case);
            }
        }

        return $cache[$key];
    }

    /**
     * Retorna uma lista completa de value, label e class.
     * Funciona em qualquer enum que implemente label() e class().
     *
     * @return array
     */
    public static function full(): array
    {
        return self::map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'class' => $case->class(),
            'badge' => $case->badge()
        ]);
    }

}
