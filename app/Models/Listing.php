<?php

namespace App\Models;

use App\Concerns\TracksAuthorship;
use App\Enums\ContactMethod;
use App\Enums\ListingStatus;
use App\Enums\OperationType;
use App\Enums\PriceDisclosure;
use Carbon\CarbonImmutable;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A business offered on the market: operation, price, contact and lifecycle (ADR-001).
 *
 * The owner is always the owner of the business. Status and every lifecycle timestamp
 * are outside the fillable list: only the Actions in App\Actions\Listings write them.
 *
 * @property int $id
 * @property int $business_id
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property ListingStatus $status
 * @property OperationType|null $primary_operation_type
 * @property int|null $stake_percent
 * @property string|null $operation_notes
 * @property string|null $title
 * @property string|null $slug
 * @property string|null $reason_for_sale
 * @property list<string>|null $highlights
 * @property bool|null $includes_stock
 * @property bool|null $includes_equipment
 * @property bool|null $includes_property
 * @property bool|null $includes_staff
 * @property bool|null $includes_intellectual_property
 * @property string|null $included_assets_notes
 * @property bool|null $premises_is_rented
 * @property PriceDisclosure $price_disclosure
 * @property int|null $asking_price
 * @property int|null $asking_price_min
 * @property int|null $asking_price_max
 * @property bool|null $is_price_negotiable
 * @property string $currency
 * @property string|null $contact_name
 * @property ContactMethod|null $preferred_contact_method
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $contact_whatsapp
 * @property string|null $contact_website_url
 * @property string|null $contact_form_url
 * @property string|null $contact_other
 * @property string|null $contact_notes
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $last_confirmed_at
 * @property CarbonImmutable|null $next_confirmation_at
 * @property CarbonImmutable|null $first_reminder_sent_at
 * @property CarbonImmutable|null $second_reminder_sent_at
 * @property CarbonImmutable|null $paused_at
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $sold_at
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable|null $suspended_at
 * @property string|null $suspension_reason
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable([
    'primary_operation_type',
    'stake_percent',
    'operation_notes',
    'title',
    'reason_for_sale',
    'highlights',
    'includes_stock',
    'includes_equipment',
    'includes_property',
    'includes_staff',
    'includes_intellectual_property',
    'included_assets_notes',
    'premises_is_rented',
    'price_disclosure',
    'asking_price',
    'asking_price_min',
    'asking_price_max',
    'is_price_negotiable',
    'contact_name',
    'preferred_contact_method',
    'contact_email',
    'contact_phone',
    'contact_whatsapp',
    'contact_website_url',
    'contact_form_url',
    'contact_other',
    'contact_notes',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory, SoftDeletes, TracksAuthorship;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'primary_operation_type' => OperationType::class,
            'stake_percent' => 'integer',
            'highlights' => 'array',
            'includes_stock' => 'boolean',
            'includes_equipment' => 'boolean',
            'includes_property' => 'boolean',
            'includes_staff' => 'boolean',
            'includes_intellectual_property' => 'boolean',
            'premises_is_rented' => 'boolean',
            'price_disclosure' => PriceDisclosure::class,
            'asking_price' => 'integer',
            'asking_price_min' => 'integer',
            'asking_price_max' => 'integer',
            'is_price_negotiable' => 'boolean',
            'preferred_contact_method' => ContactMethod::class,
            'published_at' => 'datetime',
            'last_confirmed_at' => 'datetime',
            'next_confirmation_at' => 'datetime',
            'first_reminder_sent_at' => 'datetime',
            'second_reminder_sent_at' => 'datetime',
            'paused_at' => 'datetime',
            'expired_at' => 'datetime',
            'sold_at' => 'datetime',
            'archived_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return HasMany<ListingOperationType, $this>
     */
    public function operationTypes(): HasMany
    {
        return $this->hasMany(ListingOperationType::class)->orderBy('id');
    }

    /**
     * @return HasMany<ListingFinancialMetric, $this>
     */
    public function financialMetrics(): HasMany
    {
        return $this->hasMany(ListingFinancialMetric::class);
    }

    /**
     * @return HasMany<ListingEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ListingEvent::class)->latest('id');
    }

    /**
     * @return HasMany<ListingSlugRedirect, $this>
     */
    public function slugRedirects(): HasMany
    {
        return $this->hasMany(ListingSlugRedirect::class);
    }

    /**
     * @return HasMany<ListingReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ListingReport::class);
    }

    /**
     * Listings whose business belongs to the user.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('business', fn (Builder $business) => $business->where('owner_user_id', $user->getKey()));
    }

    /**
     * Listings that are not sold nor archived: at most one per business.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->whereIn('status', ListingStatus::nonTerminal());
    }

    /**
     * What buyers may see: published, or sold within the configured window.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    #[Scope]
    protected function publiclyVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('status', ListingStatus::Published)
                ->orWhere(function (Builder $query): void {
                    $query->where('status', ListingStatus::Sold)
                        ->where('sold_at', '>=', now()->subDays((int) config('avytra.freshness.sold_visible_days')));
                });
        });
    }

    /**
     * Published listings past the first reminder threshold.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    #[Scope]
    protected function needingConfirmation(Builder $query): Builder
    {
        return $query->where('status', ListingStatus::Published)
            ->where('last_confirmed_at', '<=', now()->subDays((int) config('avytra.freshness.first_reminder_days')));
    }

    /**
     * Published listings that the scheduler must pause.
     *
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    #[Scope]
    protected function dueForExpiration(Builder $query): Builder
    {
        return $query->where('status', ListingStatus::Published)
            ->where('last_confirmed_at', '<=', now()->subDays((int) config('avytra.freshness.confirmation_period_days')));
    }

    public function owner(): User
    {
        return $this->business->owner;
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->business->isOwnedBy($user);
    }

    public function isPubliclyVisible(): bool
    {
        if ($this->status === ListingStatus::Published) {
            return true;
        }

        return $this->status === ListingStatus::Sold
            && $this->sold_at !== null
            && $this->sold_at->greaterThanOrEqualTo(now()->subDays((int) config('avytra.freshness.sold_visible_days')));
    }

    /**
     * Derived condition, not a status: published and past the first reminder threshold.
     */
    public function needsConfirmation(): bool
    {
        return $this->status === ListingStatus::Published
            && $this->last_confirmed_at !== null
            && $this->last_confirmed_at->lessThanOrEqualTo(now()->subDays((int) config('avytra.freshness.first_reminder_days')));
    }

    public function daysSinceConfirmation(): ?int
    {
        return $this->last_confirmed_at === null ? null : (int) $this->last_confirmed_at->diffInDays(now());
    }

    public function hasBeenPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * @return list<OperationType>
     */
    public function offeredOperationTypes(): array
    {
        return array_values($this->operationTypes->map(fn (ListingOperationType $row): OperationType => $row->operation_type)->all());
    }

    /**
     * The contact channel that matches the preferred method, if filled in.
     */
    public function preferredChannelValue(): ?string
    {
        if ($this->preferred_contact_method === null) {
            return null;
        }

        $value = $this->getAttribute($this->preferred_contact_method->channelColumn());

        return is_string($value) && $value !== '' ? $value : null;
    }
}
