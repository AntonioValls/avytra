<?php

namespace App\Livewire;

use App\Exceptions\GeocodingUnavailable;
use App\Livewire\Forms\LocationForm;
use App\Models\Municipality;
use App\Services\Geocoding\Geocoder;
use App\Support\Location\Coordinates;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Base of the business form and the listing wizard (step 5): both hold a LocationForm as
 * $location and render the map picker (docs/10 "Cómo obtiene el vendedor sus coordenadas").
 * The map writes latitude/longitude through wire:model; this class keeps the geocoding source
 * coherent and offers the optional address search. A base class rather than a trait so that
 * Larastan analyses it (single-file components live outside its paths).
 *
 * @property-read Collection<int, Municipality> $municipalities
 */
abstract class LocationPickerComponent extends Component
{
    public LocationForm $location;

    /**
     * Re-authorise before any change coming from the picker (never trust mount alone).
     */
    abstract protected function authorizeLocationChange(): void;

    /**
     * Municipalities of the chosen province, as rendered in the select.
     *
     * @return Collection<int, Municipality>
     */
    abstract public function municipalities(): Collection;

    public function updatedLocationLatitude(): void
    {
        $this->location->markManualPin();
    }

    public function updatedLocationLongitude(): void
    {
        $this->location->markManualPin();
    }

    public function clearLocationPoint(): void
    {
        $this->authorizeLocationChange();
        $this->location->clearPoint();
    }

    public function searchAddress(Geocoder $geocoder): void
    {
        $this->authorizeLocationChange();

        if (! $geocoder->isAvailable()) {
            return;
        }

        $this->location->validateForAddressSearch();

        $key = 'geocode:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, (int) config('avytra.geocoding.rate_limit_per_hour'))) {
            Flux::toast(variant: 'warning', text: __('Too many requests. Try again in a while.'));

            return;
        }

        RateLimiter::hit($key, 3600);

        try {
            $found = $this->location->geocodeAddress($geocoder);
        } catch (GeocodingUnavailable $exception) {
            report($exception);
            Flux::toast(variant: 'danger', text: $exception->userMessage());

            return;
        }

        Flux::toast(
            variant: $found ? 'success' : 'warning',
            text: $found
                ? __('Address found. Check the pin on the map and move it if needed.')
                : __('We could not find that address. Place the pin on the map by hand.'),
        );
    }

    /**
     * Centre of the chosen municipality: where the picker starts when there is no pin yet.
     */
    #[Computed]
    public function municipalityCentre(): ?Coordinates
    {
        if ($this->location->municipality_id === null) {
            return null;
        }

        return $this->municipalities->firstWhere('id', $this->location->municipality_id)?->centre();
    }

    #[Computed]
    public function geocoderAvailable(): bool
    {
        return app(Geocoder::class)->isAvailable();
    }
}
