<?php

namespace App\Models;

use App\Concerns\TracksAuthorship;
use App\Enums\BusinessType;
use App\Enums\EmployeeRange;
use App\Enums\LegalForm;
use App\Enums\MediaCollection;
use App\Enums\WebsiteVisibility;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The real company, owned by a user. Publishing it is a Listing (ADR-001).
 *
 * Ownership and authorship are outside the fillable list on purpose: only the
 * Actions in App\Actions\Businesses assign them.
 *
 * @property int $id
 * @property int $owner_user_id
 * @property int|null $created_by_user_id
 * @property int|null $updated_by_user_id
 * @property BusinessType $business_type
 * @property int $category_id
 * @property int|null $subcategory_id
 * @property string $name
 * @property string|null $legal_name
 * @property LegalForm|null $legal_form
 * @property bool $show_legal_form
 * @property string|null $tagline
 * @property string|null $description
 * @property int|null $founded_year
 * @property EmployeeRange|null $employee_range
 * @property string|null $website_url
 * @property WebsiteVisibility $website_visibility
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'business_type',
    'category_id',
    'subcategory_id',
    'name',
    'legal_name',
    'legal_form',
    'show_legal_form',
    'tagline',
    'description',
    'founded_year',
    'employee_range',
    'website_url',
    'website_visibility',
])]
class Business extends Model implements HasMedia
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes, TracksAuthorship;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'legal_form' => LegalForm::class,
            'employee_range' => EmployeeRange::class,
            'website_visibility' => WebsiteVisibility::class,
            'show_legal_form' => 'boolean',
            'founded_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    /**
     * The primary premises. The MVP manages a single location per business.
     *
     * @return HasOne<Location, $this>
     */
    public function location(): HasOne
    {
        return $this->hasOne(Location::class)->where('is_primary', true);
    }

    /**
     * @return HasOne<OnlineProfile, $this>
     */
    public function onlineProfile(): HasOne
    {
        return $this->hasOne(OnlineProfile::class);
    }

    /**
     * Every listing of the business, newest first (history included).
     *
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class)->latest('id');
    }

    /**
     * The one listing that is not sold nor archived, if any (domain invariant 2).
     *
     * @return HasOne<Listing, $this>
     */
    public function openListing(): HasOne
    {
        return $this->hasOne(Listing::class)->open()->latest('id');
    }

    /**
     * Whether any listing was ever published. Such a business cannot be deleted by its owner.
     */
    public function hasPublishedListings(): bool
    {
        return $this->listings()->whereNotNull('published_at')->exists();
    }

    /**
     * @param  Builder<Business>  $query
     * @return Builder<Business>
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): Builder
    {
        return $query->where('owner_user_id', $user->getKey());
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_user_id === $user->getKey();
    }

    public function requiresLocation(): bool
    {
        return $this->business_type->requiresLocation();
    }

    public function requiresOnlineProfile(): bool
    {
        return $this->business_type->requiresOnlineProfile();
    }

    /* ---------------------------------------------------------------- media */

    /**
     * Logo, cover and gallery (docs/17). Originals go to the private disk, the public
     * WebP conversions to the public one; the original is never served.
     */
    public function registerMediaCollections(): void
    {
        foreach (MediaCollection::cases() as $collection) {
            $definition = $this->addMediaCollection($collection->value)
                ->useDisk((string) config('avytra.media.originals_disk'))
                ->storeConversionsOnDisk((string) config('avytra.media.disk'));

            if ($collection->isSingleFile()) {
                $definition->singleFile();
            }
        }
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        /** @var array<string, array{width: int, height: int, fit: string}> $conversions */
        $conversions = config('avytra.media.conversions');
        $quality = (int) config('avytra.media.quality');

        foreach ($conversions as $name => $size) {
            $collections = array_values(array_filter(
                MediaCollection::cases(),
                fn (MediaCollection $collection): bool => in_array($name, $collection->conversions(), true),
            ));

            $this->addMediaConversion($name)
                ->performOnCollections(...array_map(fn (MediaCollection $collection): string => $collection->value, $collections))
                ->fit($size['fit'] === 'crop' ? Fit::Crop : Fit::Max, $size['width'], $size['height'])
                ->format('webp')
                ->quality($quality);
        }
    }

    public function cover(): ?Media
    {
        return $this->getFirstMedia(MediaCollection::Cover->value);
    }

    public function logo(): ?Media
    {
        return $this->getFirstMedia(MediaCollection::Logo->value);
    }

    /**
     * @return Collection<int, Media>
     */
    public function galleryImages(): Collection
    {
        return $this->getMedia(MediaCollection::Gallery->value)->values();
    }
}
