<?php

namespace App\Enums;

enum Disclosure: string
{
    case Exact = 'exact';
    case Range = 'range';
    case OnRequest = 'on_request';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Exact => __('Exact figure'),
            self::Range => __('Range'),
            self::OnRequest => __('On request'),
            self::Hidden => __('Hidden'),
        };
    }
}
