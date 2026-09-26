<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Availability reminders and automatic pause (docs/13): hourly so the pause moment is
// predictable within an hour; withoutOverlapping is the second barrier against duplicates.
Schedule::command('avytra:listings:process-freshness')->hourly()->withoutOverlapping();
