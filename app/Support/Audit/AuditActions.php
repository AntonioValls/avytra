<?php

namespace App\Support\Audit;

/**
 * The catalogue of audit_logs.action values (docs/16, "Auditoría") with their labels for
 * the admin audit page. Unknown values (older or future ones) fall back to the raw key.
 */
class AuditActions
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::labels());
    }

    public static function label(string $action): string
    {
        return self::labels()[$action] ?? $action;
    }

    /**
     * @return array<string, string>
     */
    private static function labels(): array
    {
        return [
            'user.created_by_admin' => __('User created by the admin'),
            'user.updated_by_admin' => __('User updated by the admin'),
            'user.password_link_sent_by_admin' => __('Set-password link sent'),
            'business.created_by_admin' => __('Business created by the admin'),
            'business.updated_by_admin' => __('Business updated by the admin'),
            'business.images_updated_by_admin' => __('Business images updated by the admin'),
            'business.owner_changed' => __('Business owner changed'),
            'listing.created_by_admin' => __('Listing created by the admin'),
            'listing.updated_by_admin' => __('Listing updated by the admin'),
            'listing.published_by_admin' => __('Listing published by the admin'),
            'listing.paused_by_admin' => __('Listing paused by the admin'),
            'listing.resumed_by_admin' => __('Listing resumed by the admin'),
            'listing.confirmed_by_admin' => __('Availability confirmed by the admin'),
            'listing.sold_by_admin' => __('Listing marked as sold by the admin'),
            'listing.archived_by_admin' => __('Listing archived by the admin'),
            'listing.suspended' => __('Listing suspended'),
            'listing.unsuspended' => __('Suspension lifted'),
            'listing.slug_changed' => __('Listing URL changed'),
            'listing.reminder_resent_by_admin' => __('Reminder resent by the admin'),
            'listing_report.resolved' => __('Report resolved'),
            'listing_report.dismissed' => __('Report dismissed'),
        ];
    }
}
