<?php

namespace App\Traits;

trait EnumIcon
{
    /**
     * Retorna value => label
     */
    public static function icons(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->icon();
        }

        return $out;
    }
}
