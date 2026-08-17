<?php

namespace App\Traits;

trait EnumOption
{
    /**
     * Imprime diretamente <option> para selects HTML.
     *
     * @param mixed $selected
     * @param string $format (normal|upper|lower|capitalize|slug)
     */
    public static function options($selected = null, string $format = 'normal'): void
    {
        foreach (self::cases() as $case) {
            $label = $case->label($format);
            $isSelected = ($selected == $case->value) ? 'selected' : '';

            echo "<option value='{$case->value}' {$isSelected}>{$label}</option>";
        }
    }

    /**
     * Retorna um array [value => label] formatado.
     */
    public static function optionsArray(string $format = 'normal'): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label($format);
        }

        return $out;
    }
}
