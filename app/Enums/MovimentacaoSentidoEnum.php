<?php

namespace App\Enums;

use App\Traits\EnumLabel;
use App\Traits\EnumOption;
use App\Traits\EnumFormat;

enum CartaoStatusEnum: string
{
    use EnumLabel, EnumOption, EnumFormat;

    case CREDITO = 'CREDITO';
    case DEBITO = 'DEBITO';

    public function label(string $format = 'normal'): string
    {
        $label = match ($this) {
            self::CREDITO => 'Crédito',
            self::DEBITO => 'Débito',
        };

        return self::formatLabel($label, $format);
    }

    public function icon(): string
    {
        $class = match ($this) {
            self::CREDITO => 'class="fa-solid fa-arrow-down fa-rotate-by" style="--fa-rotate-angle: 45deg;"',
            self::DEBITO => 'class="fa-solid fa-arrow-up fa-rotate-by text-bg-danger" style="--fa-rotate-angle: 45deg;"',
        };

        return '<i ' . $class . '></i>';
    }
}
