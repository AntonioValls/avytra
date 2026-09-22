<?php

namespace App\Enums;

enum WebsiteVisibility: string
{
    case Public = 'public';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Public => __('Public'),
            self::Private => __('Private'),
        };
    }
}
