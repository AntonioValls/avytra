<?php

namespace App\Models;

use App\Enums\Disclosure;
use App\Enums\LogisticsType;
use App\Enums\OnlineBusinessType;
use App\Enums\TechnologyPlatform;
use Database\Factories\OnlineProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Online-specific data of an online or hybrid business (docs/11-online-businesses.md).
 *
 * @property int $id
 * @property int $business_id
 * @property OnlineBusinessType $online_business_type
 * @property TechnologyPlatform|null $technology_platform
 * @property string|null $technology_platform_other
 * @property int|null $domain_registered_year
 * @property int|null $monthly_visits
 * @property Disclosure $monthly_visits_disclosure
 * @property int|null $registered_users
 * @property int|null $active_customers
 * @property int|null $monthly_orders
 * @property int|null $recurring_revenue_percent
 * @property list<string>|null $acquisition_channels
 * @property list<array{network: string, url: string}>|null $social_profiles
 * @property list<string>|null $sells_on_marketplaces
 * @property bool|null $has_stock
 * @property LogisticsType|null $logistics_type
 * @property bool|null $team_included
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'online_business_type',
    'technology_platform',
    'technology_platform_other',
    'domain_registered_year',
    'monthly_visits',
    'monthly_visits_disclosure',
    'registered_users',
    'active_customers',
    'monthly_orders',
    'recurring_revenue_percent',
    'acquisition_channels',
    'social_profiles',
    'sells_on_marketplaces',
    'has_stock',
    'logistics_type',
    'team_included',
])]
class OnlineProfile extends Model
{
    /** @use HasFactory<OnlineProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'online_business_type' => OnlineBusinessType::class,
            'technology_platform' => TechnologyPlatform::class,
            'domain_registered_year' => 'integer',
            'monthly_visits' => 'integer',
            'monthly_visits_disclosure' => Disclosure::class,
            'registered_users' => 'integer',
            'active_customers' => 'integer',
            'monthly_orders' => 'integer',
            'recurring_revenue_percent' => 'integer',
            'acquisition_channels' => 'array',
            'social_profiles' => 'array',
            'sells_on_marketplaces' => 'array',
            'has_stock' => 'boolean',
            'logistics_type' => LogisticsType::class,
            'team_included' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
