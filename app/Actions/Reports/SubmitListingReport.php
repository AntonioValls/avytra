<?php

namespace App\Actions\Reports;

use App\Enums\ListingReportReason;
use App\Enums\UserRole;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\User;
use App\Notifications\ListingReportReceived;
use Illuminate\Support\Facades\Notification;

/**
 * Stores a public report and lets the superadmin know. Anonymous reporters leave an
 * email so the platform can answer; logged-in reporters are linked by id instead.
 *
 * The IP is stored hashed only: it is used to avoid duplicates and spot abuse, never shown.
 */
class SubmitListingReport
{
    /**
     * @param  array{reason: ListingReportReason, message?: string|null, email?: string|null}  $data
     */
    public function handle(Listing $listing, ?User $reporter, ?string $ip, array $data): ListingReport
    {
        $report = $listing->reports()->create([
            'reporter_user_id' => $reporter?->getKey(),
            'reporter_email' => $reporter === null ? ($data['email'] ?? null) : null,
            'reason' => $data['reason'],
            'message' => filled($data['message'] ?? null) ? trim((string) $data['message']) : null,
            'ip_hash' => self::hashIp($ip),
        ]);

        $superadmins = User::query()->where('role', UserRole::Superadmin)->get();

        Notification::send($superadmins, new ListingReportReceived($report));

        return $report;
    }

    /**
     * Whether the same visitor already has an open report on this listing.
     */
    public static function hasOpenReport(Listing $listing, ?User $reporter, ?string $ip): bool
    {
        $hash = self::hashIp($ip);

        return $listing->reports()->open()
            ->where(function ($query) use ($reporter, $hash): void {
                if ($reporter !== null) {
                    $query->orWhere('reporter_user_id', $reporter->getKey());
                }
                if ($hash !== null) {
                    $query->orWhere('ip_hash', $hash);
                }
            })
            ->when($reporter === null && $hash === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->exists();
    }

    public static function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash('sha256', $ip.'|'.config('app.key'));
    }
}
