<?php

namespace App\Traits;

trait EnumFormat
{
    /**
     * Formata um label conforme padrão solicitado.
     *
     * @param string $label
     * @param string $format  (normal|upper|lower|capitalize|slug)
     */
    public static function formatLabel(string $label, string $format): string
    {
        return match ($format) {
            'upper'      => mb_strtoupper($label),
            'lower'      => mb_strtolower($label),
            'capitalize' => mb_convert_case($label, MB_CASE_TITLE, "UTF-8"),
            'slug'       => strtolower(str_replace(' ', '-', $label)),
            default      => $label,
        };
    }
}
