<?php

namespace App\Enums;

use App\Traits\EnumFormat;
use App\Traits\EnumLabel;
use App\Traits\EnumOption;

enum MovimentacaoTipoEnum: string
{
    use EnumLabel, EnumOption, EnumFormat;

    case CARGA_INICIAL  = 'CARGA_INICIAL';
    case RECARGA        = 'RECARGA';
    case CASHBACK       = 'CASHBACK';
    case FIDELIDADE     = 'FIDELIDADE';
    case AJUSTE_CREDITO = 'AJUSTE_CREDITO';
    case ESTORNO        = 'ESTORNO';
    case DEBITO         = 'DEBITO';
    case AJUSTE_DEBITO  = 'AJUSTE_DEBITO';
    case EXPIRACAO      = 'EXPIRACAO';

    public function label(string $format = 'normal'): string
    {
        $label = match ($this) {
            self::CARGA_INICIAL  => 'Recarga',
            self::RECARGA        => 'Recarga',
            self::CASHBACK       => 'Cashback',
            self::FIDELIDADE     => 'Fidelidade',
            self::AJUSTE_CREDITO => 'Ajuste (Credito)',
            self::ESTORNO        => 'Estorno',
            self::DEBITO         => 'Uso de Saldo',
            self::AJUSTE_DEBITO  => 'Ajuste (Debito)',
            self::EXPIRACAO      => 'Expiracao de Credito',
        };

        return self::formatLabel($label, $format);
    }

    public function sentido(): string
    {
        return match ($this) {
            self::CARGA_INICIAL,
            self::RECARGA,
            self::CASHBACK,
            self::FIDELIDADE,
            self::AJUSTE_CREDITO,
            self::ESTORNO        => 'CREDITO',
            self::DEBITO,
            self::AJUSTE_DEBITO,
            self::EXPIRACAO      => 'DEBITO',
        };
    }

    public function badge(): string
    {
        $class = match ($this) {
            self::CARGA_INICIAL, self::RECARGA => 'bg-info',
            self::CASHBACK, self::FIDELIDADE   => 'bg-success',
            self::AJUSTE_CREDITO               => 'bg-success',
            self::ESTORNO                      => 'bg-warning',
            self::DEBITO                       => 'bg-danger',
            self::AJUSTE_DEBITO                => 'bg-danger',
            self::EXPIRACAO                    => 'bg-secondary',
        };

        return '<span class="badge filled-outlined ' . $class . '">' . htmlspecialchars($this->label(), ENT_QUOTES, 'UTF-8') . '</span>';
    }
}
