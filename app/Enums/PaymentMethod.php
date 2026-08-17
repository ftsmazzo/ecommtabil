<?php

namespace App\Enums;

use App\Traits\EnumHelpers;
use App\Traits\EnumLabel;
use App\Traits\EnumOption;
use App\Traits\EnumFormat;

enum PaymentMethod: string
{
    use EnumHelpers, EnumLabel, EnumOption, EnumFormat;

    case CASH       = 'cash';
    case PIX        = 'pix';
    case CREDIT     = 'credit';
    case DEBIT      = 'debit';
    case BOLETO     = 'boleto';
    case TRANSFER   = 'transfer';
    case CHEQUE     = 'cheque';

    public function label(string $format = 'normal'): string
    {
        $label = match ($this) {
            self::CASH     => 'Dinheiro',
            self::PIX      => 'Pix',
            self::CREDIT   => 'Cartão de Crédito',
            self::DEBIT    => 'Cartão de Débito',
            self::BOLETO   => 'Boleto Bancário',
            self::TRANSFER => 'Transferência',
            self::CHEQUE   => 'Cheque',
        };

        return self::formatLabel($label, $format);
    }
}
