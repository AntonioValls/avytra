<?php

namespace App\Models;

use App\Enums\Disclosure;
use App\Enums\FinancialMetric;
use Carbon\CarbonImmutable;
use Database\Factories\ListingFinancialMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An economic figure declared in a listing, with its own disclosure (ADR-010).
 *
 * @property int $id
 * @property int $listing_id
 * @property FinancialMetric $metric
 * @property Disclosure $disclosure
 * @property int|null $amount
 * @property int|null $amount_min
 * @property int|null $amount_max
 * @property string $currency
 * @property int|null $period_year
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['metric', 'disclosure', 'amount', 'amount_min', 'amount_max', 'period_year'])]
class ListingFinancialMetric extends Model
{
    /** @use HasFactory<ListingFinancialMetricFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric' => FinancialMetric::class,
            'disclosure' => Disclosure::class,
            'amount' => 'integer',
            'amount_min' => 'integer',
            'amount_max' => 'integer',
            'period_year' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
