<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ContactRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message sent from a public listing page to its seller through the relay form
 * (docs/12, ADR-019). Read and delivery columns are outside the fillable list: only the
 * Actions and the notification write them.
 *
 * @property int $id
 * @property int $listing_id
 * @property int|null $sender_user_id
 * @property string $sender_name
 * @property string $sender_email
 * @property string|null $sender_phone
 * @property string $message
 * @property string|null $ip_hash
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $delivery_failed_at
 * @property string|null $delivery_error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['sender_user_id', 'sender_name', 'sender_email', 'sender_phone', 'message', 'ip_hash'])]
class ContactRequest extends Model
{
    /** @use HasFactory<ContactRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'delivery_failed_at' => 'datetime',
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
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /**
     * Messages received by the listings of the businesses the user owns.
     *
     * @param  Builder<ContactRequest>  $query
     * @return Builder<ContactRequest>
     */
    #[Scope]
    protected function receivedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('listing.business', fn (Builder $business) => $business->where('owner_user_id', $user->getKey()));
    }

    /**
     * @param  Builder<ContactRequest>  $query
     * @return Builder<ContactRequest>
     */
    #[Scope]
    protected function unread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * @param  Builder<ContactRequest>  $query
     * @return Builder<ContactRequest>
     */
    #[Scope]
    protected function undelivered(Builder $query): Builder
    {
        return $query->whereNotNull('delivery_failed_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function wasDelivered(): bool
    {
        return $this->delivery_failed_at === null;
    }
}
