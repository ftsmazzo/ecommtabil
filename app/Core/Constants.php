<?php

namespace App\Core;

use ReflectionClass;

class Constants
{
    /**
     * Retorna todas as constantes de uma classe.
     *
     * @param string $class Nome da classe (ex.: EmpresaStatus::class)
     * @param string|null $prefix Prefixo opcional para filtrar (ex.: 'STATUS_')
     * @param bool $valuesOnly Se TRUE, retorna apenas os valores. FALSE = chave => valor
     * @return array
     */
    public static function getConstants(string $class, ?string $prefix = null, bool $valuesOnly = false): array
    {
        $ref = new ReflectionClass($class);
        $constants = $ref->getConstants();

        // filtrar por prefixo, se fornecido
        if ($prefix !== null) {
            $constants = array_filter(
                $constants,
                fn($key) => str_starts_with($key, $prefix),
                ARRAY_FILTER_USE_KEY
            );
        }

        // retornar apenas valores se solicitado
        if ($valuesOnly) {
            return array_values($constants);
        }

        return $constants;
    }
}
