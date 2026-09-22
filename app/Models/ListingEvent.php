<?php

namespace App\Models;

use App\Enums\ListingEventType;
use Carbon\CarbonImmutable;
use Database\Factories\ListingEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable entry of a listing's history (transitions, confirmations, reminders).
 *
 * @property int $id
 * @property int $listing_id
 * @property ListingEventType $type
 * @property int|null $actor_user_id
 * @property int|null $on_behalf_of_user_id
 * @property array<string, mixed>|null $payload
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['type', 'actor_user_id', 'on_behalf_of_user_id', 'payload'])]
class ListingEvent extends Model
{
    /** @use HasFactory<ListingEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ListingEventType::class,
            'payload' => 'array',
            'created_at' => 'datetime',
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function onBehalfOf(): BelongsTo
    {
        return $this->belongsTo(User::class, 'on_behalf_of_user_id');
    }
}
