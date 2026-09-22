<?php

namespace App\Enums;

enum GeocodingSource: string
{
    case ManualPin = 'manual_pin';
    case Geocoder = 'geocoder';
    case MunicipalityCentroid = 'municipality_centroid';

    public function label(): string
    {
        return match ($this) {
            self::ManualPin => __('Pin placed by the owner'),
            self::Geocoder => __('Geocoded address'),
            self::MunicipalityCentroid => __('Municipality centre'),
        };
    }
}
