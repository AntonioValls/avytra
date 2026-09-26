<?php

use App\Support\Monitoring\SchedulerHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Availability reminders and automatic pause (docs/13): hourly so the pause moment is
// predictable within an hour; withoutOverlapping is the second barrier against duplicates.
Schedule::command('avytra:listings:process-freshness')->hourly()->withoutOverlapping();

// Heartbeat: proves the cron is running schedule:run; the admin summary warns when it goes stale.
Schedule::call(fn () => app(SchedulerHeartbeat::class)->beat())->everyMinute()->name('avytra:scheduler-heartbeat');
