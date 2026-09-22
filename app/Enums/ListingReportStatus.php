<?php

namespace App\Enums;

/**
 * Inbox state of a report. Resolved and dismissed are both closed; they differ in
 * whether the superadmin found the report justified.
 */
enum ListingReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Resolved => __('Resolved'),
            self::Dismissed => __('Dismissed'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::Resolved => 'lime',
            self::Dismissed => 'zinc',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
