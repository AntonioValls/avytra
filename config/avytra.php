<?php

/*
|--------------------------------------------------------------------------
| AVYTRA application settings
|--------------------------------------------------------------------------
|
| Every product-level number lives here. Application code reads these values
| through config('avytra.*'); no thresholds are hard-coded elsewhere.
| See docs/13-freshness-and-notifications.md and docs/10-location-and-maps.md.
|
*/

return [

    'freshness' => [
        // Days without confirmation after which a published listing is paused automatically.
        'confirmation_period_days' => (int) env('AVYTRA_CONFIRMATION_PERIOD_DAYS', 60),
        // Days after the last confirmation at which the first and second reminders are sent.
        'first_reminder_days' => (int) env('AVYTRA_FIRST_REMINDER_DAYS', 45),
        'second_reminder_days' => (int) env('AVYTRA_SECOND_REMINDER_DAYS', 55),
        // Validity of the signed link in reminder emails.
        'confirmation_link_ttl_days' => 20,
        // Days a sold listing remains publicly visible (with a "sold" mark).
        'sold_visible_days' => 30,
    ],

    'contact' => [
        // "Show phone / email" clicks allowed per IP and hour on public listing pages.
        'reveal_rate_limit_per_hour' => 20,
    ],

    'reports' => [
        'rate_limit_per_hour' => 3,
        // A form submitted faster than this is treated as a bot (honeypot companion).
        'min_seconds_to_submit' => 3,
        'message_max_length' => 1000,
    ],

    'location' => [
        // Seeds the deterministic offset applied to approximate public locations.
        'jitter_salt' => env('AVYTRA_LOCATION_SALT', ''),
        // "Approximate" visibility: the real point is moved between min and max metres
        // and shown as a circle of approximate_radius_m (always larger than the max offset).
        'approximate_radius_m' => 700,
        'approximate_offset_min_m' => 250,
        'approximate_offset_max_m' => 600,
        // "Municipality only" visibility: circle over the municipality centroid, sized by population.
        'city_only_radius_m' => [
            'default' => 3000,
            // population upper bound (exclusive) => radius in metres
            'by_population' => [
                5000 => 1500,
                20000 => 2000,
                100000 => 3000,
                500000 => 4000,
            ],
            'max' => 5000,
        ],
    ],

    'map' => [
        'style_url' => env('MAP_STYLE_URL', 'https://tiles.openfreemap.org/styles/liberty'),
    ],

    'geocoding' => [
        // "null" disables address search in forms; other drivers arrive in Phase 5.
        'driver' => env('GEOCODING_DRIVER', 'null'),
    ],

    'support' => [
        'email' => env('AVYTRA_SUPPORT_EMAIL'),
        'phone' => env('AVYTRA_SUPPORT_PHONE'),
    ],

    'rate_limits' => [
        'register_per_hour' => 5,
        'public_per_minute' => 60,
    ],

    'pagination' => [
        'panel_cards' => 12,
        'admin_rows' => 25,
        'public_cards' => 24,
    ],

    'public' => [
        // Listings shown on the home page and as related listings on a listing page.
        'home_latest_listings' => 8,
        'related_listings' => 4,
        // Cached aggregates (counts per category/province) in minutes.
        'aggregates_cache_minutes' => 15,
        // Explore price filter bounds (EUR).
        'price_filter_max' => 50000000,
    ],

    'limits' => [
        // Business description (plain text): maximum stored, minimum required to publish.
        'description_max_length' => 5000,
        'description_min_length_to_publish' => 200,
        // Listing title and highlights.
        'title_max_length' => 90,
        'highlights_max' => 5,
        'highlight_max_length' => 120,
    ],

];
