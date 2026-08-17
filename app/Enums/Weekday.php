<?php

namespace App\Enums;

use App\Traits\EnumHelpers;
use App\Traits\EnumLabelTrait;
use App\Traits\EnumOptionTrait;
use App\Traits\EnumFormatTrait;

enum Weekday: int
{
    use EnumHelpers, EnumLabelTrait, EnumOptionTrait, EnumFormatTrait;

    case MON = 1;
    case TUE = 2;
    case WED = 3;
    case THU = 4;
    case FRI = 5;
    case SAT = 6;
    case SUN = 7;

    public function label(string $format = 'normal'): string
    {
        $label = match ($this) {
            self::MON => 'Segunda-feira',
            self::TUE => 'Terça-feira',
            self::WED => 'Quarta-feira',
            self::THU => 'Quinta-feira',
            self::FRI => 'Sexta-feira',
            self::SAT => 'Sábado',
            self::SUN => 'Domingo',
        };

        return self::formatLabel($label, $format);
    }
}
