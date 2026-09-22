<?php

namespace App\Enums;

enum PriceDisclosure: string
{
    case Exact = 'exact';
    case Range = 'range';
    case OnRequest = 'on_request';

    public function label(): string
    {
        return match ($this) {
            self::Exact => __('Exact price'),
            self::Range => __('Price range'),
            self::OnRequest => __('On request'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Exact => __('The asking price is shown.'),
            self::Range => __('A minimum and a maximum are shown.'),
            self::OnRequest => __('Buyers ask you for the price. Common in this market.'),
        };
    }
}
