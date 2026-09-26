<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

test('the freshness command is scheduled hourly without overlapping', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'avytra:listings:process-freshness'));

    expect($events)->toHaveCount(1);

    /** @var Event $event */
    $event = $events->first();

    expect($event->expression)->toBe('0 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
