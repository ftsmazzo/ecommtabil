<?php

namespace App\Enums;

use App\Traits\EnumLabel;
use App\Traits\EnumOption;
use App\Traits\EnumFormat;

enum MovimentacaoOrigemEnum: string
{
    use EnumLabel, EnumOption, EnumFormat;

    case ADMIN   = 'admin';
    case PDV     = 'pdv';
    case SISTEMA = 'sistema';
    case PORTAL  = 'portal';

    public function label(string $format = 'normal'): string
    {
        $label = match ($this) {
            self::ADMIN   => 'Painel Admin',
            self::PDV     => 'PDV',
            self::SISTEMA => 'Sistema',
            self::PORTAL  => 'Portal do Cliente',
        };

        return self::formatLabel($label, $format);
    }
}
