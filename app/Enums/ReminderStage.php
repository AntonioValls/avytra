<?php

namespace App\Enums;

/**
 * The two availability reminders sent before a published listing is paused automatically
 * (docs/13-freshness-and-notifications.md). Thresholds live in config/avytra.php.
 */
enum ReminderStage: string
{
    case First = 'first';
    case Second = 'second';

    /**
     * Days after the last confirmation at which this reminder is due.
     */
    public function days(): int
    {
        return (int) config(match ($this) {
            self::First => 'avytra.freshness.first_reminder_days',
            self::Second => 'avytra.freshness.second_reminder_days',
        });
    }

    /**
     * Days after the last confirmation at which the next step happens (the second reminder,
     * or the automatic pause). A listing past this point is handled by that step instead.
     */
    public function nextThresholdDays(): int
    {
        return match ($this) {
            self::First => self::Second->days(),
            self::Second => (int) config('avytra.freshness.confirmation_period_days'),
        };
    }

    /**
     * Column of listings that records when this reminder was sent.
     */
    public function sentAtColumn(): string
    {
        return match ($this) {
            self::First => 'first_reminder_sent_at',
            self::Second => 'second_reminder_sent_at',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::First => __('First reminder'),
            self::Second => __('Second reminder'),
        };
    }
}
