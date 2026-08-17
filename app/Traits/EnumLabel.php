<?php

namespace App\Traits;

trait EnumLabel
{
    /**
     * Retorna value => label
     */
    public static function labels(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
