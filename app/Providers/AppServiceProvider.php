<?php

namespace App\Providers;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
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
    }
}
