<?php

namespace App\Providers;

use App\Services\Geocoding\Geocoder;
use App\Services\Geocoding\NominatimGeocoder;
use App\Services\Geocoding\NullGeocoder;
use App\Support\Location\PublicPointDeriver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Blaze\Blaze;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PublicPointDeriver::class, function (): PublicPointDeriver {
            /** @var array{jitter_salt: string, approximate_radius_m: int, approximate_offset_min_m: int, approximate_offset_max_m: int, city_only_radius_m: array{default: int, by_population: array<int, int>, max: int}} $location */
            $location = config('avytra.location');

            return new PublicPointDeriver(
                salt: $location['jitter_salt'],
                approximateRadiusM: $location['approximate_radius_m'],
                minOffsetM: $location['approximate_offset_min_m'],
                maxOffsetM: $location['approximate_offset_max_m'],
                cityDefaultRadiusM: $location['city_only_radius_m']['default'],
                cityRadiusByPopulation: $location['city_only_radius_m']['by_population'],
                cityMaxRadiusM: $location['city_only_radius_m']['max'],
            );
        });

        $this->app->bind(Geocoder::class, function (): Geocoder {
            /** @var array{driver: string, cache_days: int, nominatim: array{base_url: string, user_agent: string, email: string|null, requests_per_second: int, timeout_seconds: int}} $geocoding */
            $geocoding = config('avytra.geocoding');

            return match ($geocoding['driver']) {
                'nominatim' => new NominatimGeocoder(
                    baseUrl: $geocoding['nominatim']['base_url'],
                    userAgent: $geocoding['nominatim']['user_agent'],
                    email: $geocoding['nominatim']['email'],
                    requestsPerSecond: $geocoding['nominatim']['requests_per_second'],
                    timeoutSeconds: $geocoding['nominatim']['timeout_seconds'],
                    cacheDays: $geocoding['cache_days'],
                ),
                default => new NullGeocoder,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureBlaze();
    }

    /**
     * Blaze compiles the presentational components of the marketplace (the ones rendered
     * dozens of times per page) into plain PHP functions. Opt-in per file on purpose: the
     * folder also holds Livewire single-file components and layout pieces that gain nothing.
     */
    protected function configureBlaze(): void
    {
        $blaze = Blaze::optimize();

        foreach (['listing-card', 'price', 'freshness-badge', 'listing-status-badge', 'empty-state'] as $component) {
            $blaze->in(resource_path("views/components/{$component}.blade.php"));
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Named rate limiters used by public and semi-public actions.
     * Auth-specific limiters live in FortifyServiceProvider.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(config('avytra.rate_limits.register_per_hour'))->by($request->ip());
        });

        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(config('avytra.rate_limits.public_per_minute'))->by($request->ip());
        });

        // Livewire actions consult these two through RateLimiter::attempt() with the same names.
        RateLimiter::for('contact-reveal', function (Request $request) {
            return Limit::perHour(config('avytra.contact.reveal_rate_limit_per_hour'))->by($request->ip());
        });

        RateLimiter::for('report', function (Request $request) {
            return Limit::perHour(config('avytra.reports.rate_limit_per_hour'))->by($request->ip());
        });

        // Image uploads in the business images component, per authenticated user (docs/16).
        RateLimiter::for('image-upload', function (Request $request) {
            return Limit::perHour(config('avytra.media.upload_rate_limit_per_hour'))->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        // Confirmation page linked from reminder emails (docs/16), per authenticated user.
        RateLimiter::for('confirmation', function (Request $request) {
            return Limit::perHour(config('avytra.rate_limits.confirmation_per_hour'))->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        // Address search in the location picker (LocationPickerComponent), per authenticated user.
        RateLimiter::for('geocode', function (Request $request) {
            return Limit::perHour(config('avytra.geocoding.rate_limit_per_hour'))->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
    }
}
