<?php

namespace App\Enums;

use App\Traits\EnumHelpers;
use App\Traits\EnumLabel;
use App\Traits\EnumOption;
use App\Traits\EnumFormat;

enum Month: int
{
    use EnumHelpers, EnumLabel, EnumOption, EnumFormat;

    case JAN = 1;
    case FEB = 2;
    case MAR = 3;
    case APR = 4;
    case MAY = 5;
    case JUN = 6;
    case JUL = 7;
    case AUG = 8;
    case SEP = 9;
    case OCT = 10;
    case NOV = 11;
    case DEC = 12;

    public function label(string $format = 'normal'): string
    {
        $label = match ($this) {
            self::JAN => 'Janeiro',
            self::FEB => 'Fevereiro',
            self::MAR => 'Março',
            self::APR => 'Abril',
            self::MAY => 'Maio',
            self::JUN => 'Junho',
            self::JUL => 'Julho',
            self::AUG => 'Agosto',
            self::SEP => 'Setembro',
            self::OCT => 'Outubro',
            self::NOV => 'Novembro',
            self::DEC => 'Dezembro',
        };

        return self::formatLabel($label, $format);
    }
}
