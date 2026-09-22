<?php

namespace App\Enums;

enum BusinessType: string
{
    case Physical = 'physical';
    case Online = 'online';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Physical => __('Physical business'),
            self::Online => __('Online business'),
            self::Hybrid => __('Hybrid business'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Physical => __('A shop, restaurant, workshop or any business that operates from premises.'),
            self::Online => __('An ecommerce, SaaS, marketplace or any business that operates only online.'),
            self::Hybrid => __('A business with premises and a relevant online activity.'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Physical => 'building-storefront',
            self::Online => 'globe-alt',
            self::Hybrid => 'squares-plus',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Physical => 'zinc',
            self::Online => 'blue',
            self::Hybrid => 'indigo',
        };
    }

    /**
     * Physical and hybrid businesses operate from premises and need a location.
     */
    public function requiresLocation(): bool
    {
        return $this !== self::Online;
    }

    /**
     * Online and hybrid businesses need an online profile.
     */
    public function requiresOnlineProfile(): bool
    {
        return $this !== self::Physical;
    }
}
