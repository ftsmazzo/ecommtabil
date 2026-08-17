<?php

namespace App\Traits;

trait EnumClass
{
    /**
     * Retorna value => class bootstrap
     */
    public static function classes(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->class();
        }

        return $out;
    }
}
