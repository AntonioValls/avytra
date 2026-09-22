<?php

namespace App\Enums;

enum TechnologyPlatform: string
{
    case Shopify = 'shopify';
    case Woocommerce = 'woocommerce';
    case Prestashop = 'prestashop';
    case Magento = 'magento';
    case LaravelCustom = 'laravel_custom';
    case Wordpress = 'wordpress';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Shopify => 'Shopify',
            self::Woocommerce => 'WooCommerce',
            self::Prestashop => 'PrestaShop',
            self::Magento => 'Magento',
            self::LaravelCustom => __('Laravel or custom development'),
            self::Wordpress => 'WordPress',
            self::Other => __('Other platform'),
        };
    }
}
