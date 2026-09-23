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
        // MapLibre style (docs/10, ADR-005). Any MapLibre-compatible style URL works.
        'style_url' => env('MAP_STYLE_URL', 'https://tiles.openfreemap.org/styles/liberty'),
        // Where the picker starts before a municipality is chosen (Spain).
        'default_centre' => ['latitude' => 40.2, 'longitude' => -3.7],
        'zoom' => [
            'country' => 5,
            'municipality' => 13,
            'exact' => 16,
        ],
    ],

    'geocoding' => [
        // "null" disables address search in forms; "nominatim" is for development / low volume only.
        'driver' => env('GEOCODING_DRIVER', 'null'),
        // Address searches allowed per user and hour.
        'rate_limit_per_hour' => 30,
        // Results are cached per normalised query.
        'cache_days' => 30,
        'nominatim' => [
            'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
            // Nominatim requires an identifying User-Agent and prefers a contact email.
            'user_agent' => env('NOMINATIM_USER_AGENT', 'AVYTRA/1.0'),
            'email' => env('NOMINATIM_EMAIL'),
            'requests_per_second' => 1,
            'timeout_seconds' => 5,
        ],
    ],

    'media' => [
        // Where the original uploads live (never served) and where the public WebP conversions go.
        'originals_disk' => env('MEDIA_ORIGINALS_DISK', 'local'),
        'disk' => env('MEDIA_DISK', 'public'),
        // Gallery size per business (docs/17).
        'gallery_max' => 12,
        // Upload validation: size in kilobytes and pixel bounds.
        'max_kilobytes' => 8192,
        'min_width' => 600,
        'min_height' => 400,
        'logo_min_width' => 128,
        'logo_min_height' => 128,
        'max_width' => 8000,
        'max_height' => 8000,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        // Uploads allowed per user and hour.
        'upload_rate_limit_per_hour' => 30,
        // Conversions (WebP). Widths/heights in pixels; "crop" fills the box, "max" fits inside without upscaling.
        'quality' => 82,
        'conversions' => [
            'thumb' => ['width' => 400, 'height' => 250, 'fit' => 'crop'],
            'card' => ['width' => 800, 'height' => 500, 'fit' => 'crop'],
            'detail' => ['width' => 1600, 'height' => 1600, 'fit' => 'max'],
            'og' => ['width' => 1200, 'height' => 630, 'fit' => 'crop'],
            'logo' => ['width' => 256, 'height' => 256, 'fit' => 'max'],
        ],
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
