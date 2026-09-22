<?php

test('freshness thresholds are ordered: first reminder, second reminder, automatic pause', function () {
    $first = config('avytra.freshness.first_reminder_days');
    $second = config('avytra.freshness.second_reminder_days');
    $period = config('avytra.freshness.confirmation_period_days');

    expect($first)->toBeInt()->toBeGreaterThan(0)
        ->and($second)->toBeGreaterThan($first)
        ->and($period)->toBeGreaterThan($second);
});

test('the approximate location circle always contains the real point', function () {
    $location = config('avytra.location');

    expect($location['approximate_offset_min_m'])->toBeInt()->toBeGreaterThan(0)
        ->and($location['approximate_offset_max_m'])->toBeGreaterThan($location['approximate_offset_min_m'])
        ->and($location['approximate_radius_m'])->toBeGreaterThan($location['approximate_offset_max_m'])
        ->and($location['city_only_radius_m']['max'])->toBeGreaterThanOrEqual(max($location['city_only_radius_m']['by_population']));
});

test('the application runs in Spanish and on Madrid time', function () {
    expect(config('app.locale'))->toBe('es')
        ->and(config('app.fallback_locale'))->toBe('es')
        ->and(config('app.timezone'))->toBe('Europe/Madrid');
});
