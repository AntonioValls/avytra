<?php

namespace App\Support\Listings;

use App\Enums\Disclosure;
use App\Enums\PriceDisclosure;
use App\Models\Listing;
use App\Models\ListingFinancialMetric;
use Illuminate\Support\Number;

/**
 * Money as it reads in the interface: whole euros in Spanish format, and the "on request"
 * wording whenever the seller chose not to disclose a figure or it is missing.
 */
final class PriceFormatter
{
    public static function amount(int $amount, string $currency = 'EUR'): string
    {
        return (string) Number::currency($amount, $currency, locale: 'es', precision: 0);
    }

    public static function range(int $min, int $max, string $currency = 'EUR'): string
    {
        return self::amount($min, $currency).' – '.self::amount($max, $currency);
    }

    public static function forListing(Listing $listing): string
    {
        return match ($listing->price_disclosure) {
            PriceDisclosure::Exact => $listing->asking_price === null ? self::onRequest() : self::amount($listing->asking_price, $listing->currency),
            PriceDisclosure::Range => $listing->asking_price_min === null || $listing->asking_price_max === null
                ? self::onRequest()
                : self::range($listing->asking_price_min, $listing->asking_price_max, $listing->currency),
            PriceDisclosure::OnRequest => self::onRequest(),
        };
    }

    /**
     * Null for hidden metrics: they must not be printed at all.
     */
    public static function forMetric(ListingFinancialMetric $metric): ?string
    {
        if ($metric->disclosure === Disclosure::Hidden) {
            return null;
        }

        return match ($metric->disclosure) {
            Disclosure::Exact => $metric->amount === null ? self::onRequest() : self::amount($metric->amount, $metric->currency),
            Disclosure::Range => $metric->amount_min === null || $metric->amount_max === null
                ? self::onRequest()
                : self::range($metric->amount_min, $metric->amount_max, $metric->currency),
            Disclosure::OnRequest => self::onRequest(),
        };
    }

    public static function onRequest(): string
    {
        return __('On request');
    }
}
