<?php

namespace App\Enums;

/**
 * Entries of listing_events: every transition plus freshness actions.
 */
enum ListingEventType: string
{
    case Created = 'created';
    case Published = 'published';
    case Paused = 'paused';
    case Resumed = 'resumed';
    case Confirmed = 'confirmed';
    case ReminderSent = 'reminder_sent';
    case ReminderFailed = 'reminder_failed';
    case Expired = 'expired';
    case Sold = 'sold';
    case Archived = 'archived';
    case Suspended = 'suspended';
    case Unsuspended = 'unsuspended';
    case SlugChanged = 'slug_changed';

    public function label(): string
    {
        return match ($this) {
            self::Created => __('Draft created'),
            self::Published => __('Published'),
            self::Paused => __('Paused'),
            self::Resumed => __('Resumed'),
            self::Confirmed => __('Availability confirmed'),
            self::ReminderSent => __('Reminder sent'),
            self::ReminderFailed => __('Reminder could not be delivered'),
            self::Expired => __('Paused automatically'),
            self::Sold => __('Marked as sold'),
            self::Archived => __('Archived'),
            self::Suspended => __('Suspended'),
            self::Unsuspended => __('Suspension lifted'),
            self::SlugChanged => __('URL changed'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Created => 'document-plus',
            self::Published => 'rocket-launch',
            self::Paused => 'pause',
            self::Resumed => 'play',
            self::Confirmed => 'check-badge',
            self::ReminderSent => 'bell',
            self::ReminderFailed => 'bell-alert',
            self::Expired => 'clock',
            self::Sold => 'hand-thumb-up',
            self::Archived => 'archive-box',
            self::Suspended => 'no-symbol',
            self::Unsuspended => 'lock-open',
            self::SlugChanged => 'link',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Published, self::Resumed, self::Confirmed, self::Unsuspended => 'lime',
            self::Sold => 'blue',
            self::Expired, self::ReminderSent => 'amber',
            self::Suspended, self::ReminderFailed => 'red',
            default => 'zinc',
        };
    }
}
