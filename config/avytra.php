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
        'reveal_rate_limit_per_hour' => 20,
    ],

    'reports' => [
        'rate_limit_per_hour' => 3,
    ],

    'location' => [
        // Seeds the deterministic offset applied to approximate public locations.
        'jitter_salt' => env('AVYTRA_LOCATION_SALT', ''),
        'approximate_radius_m' => 700,
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

];
