<?php

namespace App\Enums;

enum CanalVendaTipo: string
{
    case DELIVERY    = "delivery";
    case MARKETPLACE = "marketplace";
    case ECOMMERCE   = "ecommerce";
    case LOJA_FISICA = "loja_fisica";
    case APP         = "app";
    case OUTRO       = "outro";

    public function label(): string
    {
        return match ($this) {
            self::DELIVERY    => "Delivery",
            self::MARKETPLACE => "Marketplace",
            self::ECOMMERCE   => "E-commerce",
            self::LOJA_FISICA => "Loja Física",
            self::APP         => "Aplicativo",
            self::OUTRO       => "Outro",
        };
    }

    public static function options(): array
    {
        return array_column(
            array_map(fn ($case) => ["value" => $case->value, "label" => $case->label()], self::cases()),
            "label",
            "value"
        );
    }
}
