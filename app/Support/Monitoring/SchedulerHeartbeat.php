<?php

namespace App\Support\Monitoring;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Proof that `schedule:run` is being executed (docs/13, "Fallos y reintentos"): a scheduled
 * closure stores the current time every minute and the admin summary warns when it is stale.
 * Without a running scheduler, reminders and automatic pauses silently stop.
 */
final class SchedulerHeartbeat
{
    public const CACHE_KEY = 'avytra.scheduler.heartbeat';

    public function beat(): void
    {
        Cache::forever(self::CACHE_KEY, now()->toIso8601String());
    }

    public function lastBeatAt(): ?CarbonImmutable
    {
        $value = Cache::get(self::CACHE_KEY);

        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }

    /**
     * True while the last beat is younger than avytra.monitoring.scheduler_stale_minutes.
     */
    public function isHealthy(): bool
    {
        $last = $this->lastBeatAt();

        return $last !== null && $last->greaterThan(now()->subMinutes((int) config('avytra.monitoring.scheduler_stale_minutes')));
    }
}
