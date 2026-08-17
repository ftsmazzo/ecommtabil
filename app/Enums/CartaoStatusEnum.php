<?php

namespace App\Enums;

use App\Traits\EnumLabel;
use App\Traits\EnumOption;
use App\Traits\EnumFormat;

enum CartaoStatusEnum: string
{
    use EnumLabel, EnumOption, EnumFormat;

    case ATIVO = 'ATIVO';
    case BLOQUEADO = 'BLOQUEADO';
    case INATIVO = 'INATIVO';
    case VENCIDO = 'VENCIDO';
    case CANCELADO = 'CANCELADO';

    public function label(string $format = 'normal'): string
    {
        $label = match ($this) {
            self::ATIVO => 'Ativo',
            self::BLOQUEADO => 'Bloqueado',
            self::INATIVO => 'Inativo',
            self::VENCIDO => 'Vencido',
            self::CANCELADO => 'Cancelado',
        };

        return self::formatLabel($label, $format);
    }

    public function badge(): string
    {
        $class = match ($this) {
            self::ATIVO => 'bg-success',
            self::BLOQUEADO => 'bg-warning',
            self::INATIVO => 'bg-dark',
            self::VENCIDO => 'bg-danger',
            self::CANCELADO => 'bg-secondary',
        };

        return '<span class="badge filled-outlined ' . $class . '">' . htmlspecialchars($this->label(), ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
