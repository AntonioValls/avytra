<?php

namespace App\Models;

use App\Concerns\TracksAuthorship;
use App\Enums\BusinessType;
use App\Enums\EmployeeRange;
use App\Enums\LegalForm;
use App\Enums\WebsiteVisibility;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * The real company, owned by a user. Publishing it is a Listing (Phase 3).
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
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, SoftDeletes, TracksAuthorship;

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
}
