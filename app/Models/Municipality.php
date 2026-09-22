<?php

namespace App\Models;

use App\Support\Location\Coordinates;
use Database\Factories\MunicipalityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Municipality with its centroid. The centroid is the public point for "city only" locations.
 *
 * @property int $id
 * @property int $province_id
 * @property string $country_code
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $population
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['province_id', 'country_code', 'code', 'name', 'slug', 'latitude', 'longitude', 'population'])]
class Municipality extends Model
{
    /** @use HasFactory<MunicipalityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'population' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function centre(): ?Coordinates
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return new Coordinates($this->latitude, $this->longitude);
    }
}
