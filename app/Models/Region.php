<?php

namespace App\Models;

use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Autonomous community (or autonomous city). Part of the seeded geographic catalogue.
 *
 * @property int $id
 * @property string $country_code
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['country_code', 'code', 'name', 'slug'])]
class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    /**
     * @return HasMany<Province, $this>
     */
    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class)->orderBy('name');
    }
}
