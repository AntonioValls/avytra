<?php

namespace App\Enums;

enum OnlineBusinessType: string
{
    case Ecommerce = 'ecommerce';
    case Saas = 'saas';
    case Marketplace = 'marketplace';
    case Content = 'content';
    case Affiliate = 'affiliate';
    case App = 'app';
    case Service = 'service';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ecommerce => __('Ecommerce'),
            self::Saas => __('SaaS'),
            self::Marketplace => __('Marketplace'),
            self::Content => __('Content or media'),
            self::Affiliate => __('Affiliate'),
            self::App => __('App'),
            self::Service => __('Online service'),
            self::Other => __('Other online business'),
        };
    }
}
