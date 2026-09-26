<?php

namespace App\Support\Listings;

use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * The link reminder emails point to: the authenticated confirmation page of the panel,
 * signed for a limited time so an old email stops leading to the action (ADR-007).
 *
 * Validation rebuilds the canonical route URL instead of trusting the incoming request URL,
 * so it holds behind proxies that rewrite host or scheme and inside Livewire requests.
 */
class ConfirmationLink
{
    public const ROUTE = 'panel.listings.confirm';

    public static function for(Listing $listing): string
    {
        return URL::temporarySignedRoute(
            self::ROUTE,
            now()->addDays((int) config('avytra.freshness.confirmation_link_ttl_days')),
            ['listing' => $listing],
        );
    }

    /**
     * Whether the request carries a signature made by for() for this listing that has not expired.
     */
    public static function isValid(Listing $listing, Request $request): bool
    {
        $expires = $request->query('expires');
        $signature = $request->query('signature');

        if (! is_string($expires) || ! ctype_digit($expires) || ! is_string($signature) || $signature === '') {
            return false;
        }

        if (now()->getTimestamp() > (int) $expires) {
            return false;
        }

        $original = route(self::ROUTE, ['listing' => $listing, 'expires' => (int) $expires]);

        foreach (self::keys() as $key) {
            if (hash_equals(hash_hmac('sha256', $original, $key), $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The current application key plus the previous ones accepted during key rotation.
     *
     * @return list<string>
     */
    private static function keys(): array
    {
        $keys = [(string) config('app.key'), ...array_values((array) config('app.previous_keys', []))];

        return array_values(array_filter($keys, fn (mixed $key): bool => is_string($key) && $key !== ''));
    }
}
