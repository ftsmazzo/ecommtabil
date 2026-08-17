<?php

namespace App\Enums;

use App\Traits\EnumHelpers;
use App\Traits\EnumLabel;
use App\Traits\EnumOption;
use App\Traits\EnumFormat;

enum Gender: string
{
    use EnumHelpers, EnumLabel, EnumOption, EnumFormat;

    case M = 'M';
    case F = 'F';
    case O = 'O';

    public function label(string $format = 'normal'): string
    {
        $label = match ($this) {
            self::M => 'Masculino',
            self::F => 'Feminino',
            self::O => 'Outro',
        };

        return self::formatLabel($label, $format);
    }
}
