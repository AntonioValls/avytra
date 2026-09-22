<?php

namespace App\Models;

use App\Enums\ListingReportReason;
use App\Enums\ListingReportStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ListingReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public report about a listing, handled by the superadmin (docs/08, docs/09).
 *
 * Resolution columns are outside the fillable list: only ResolveListingReport writes them.
 *
 * @property int $id
 * @property int $listing_id
 * @property int|null $reporter_user_id
 * @property string|null $reporter_email
 * @property ListingReportReason $reason
 * @property string|null $message
 * @property ListingReportStatus $status
 * @property string|null $ip_hash
 * @property int|null $resolved_by_user_id
 * @property CarbonImmutable|null $resolved_at
 * @property string|null $resolution_notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['reporter_user_id', 'reporter_email', 'reason', 'message', 'ip_hash'])]
class ListingReport extends Model
{
    /** @use HasFactory<ListingReportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ListingReportReason::class,
            'status' => ListingReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    /**
     * @param  Builder<ListingReport>  $query
     * @return Builder<ListingReport>
     */
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->where('status', ListingReportStatus::Open);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
