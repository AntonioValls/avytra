<?php

namespace App\Enums;

enum LocationVisibility: string
{
    case Exact = 'exact';
    case Approximate = 'approximate';
    case CityOnly = 'city_only';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Exact => __('Exact address'),
            self::Approximate => __('Approximate area'),
            self::CityOnly => __('Municipality only'),
            self::Hidden => __('Do not show the location'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Exact => __('The address and an exact pin are shown publicly.'),
            self::Approximate => __('Recommended. We show an area of about 700 m; nobody will see your exact address.'),
            self::CityOnly => __('Only the municipality and the province are shown, with a wide area on the map.'),
            self::Hidden => __('Only the province is shown. No map.'),
        };
    }

    /**
     * Exact and approximate rendering only make sense when the owner placed a real point.
     */
    public function needsPrivateCoordinates(): bool
    {
        return $this === self::Exact || $this === self::Approximate;
    }
}
