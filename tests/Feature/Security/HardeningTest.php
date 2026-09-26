<?php

use App\Models\User;
use App\Support\Monitoring\SchedulerHeartbeat;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

test('every web response carries the security headers', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('the registration form carries a honeypot and refuses submissions that fill it or arrive too fast', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('name="website"', false)
        ->assertSee('name="form_opened_at"', false);

    $this->post(route('register.store'), [
        'name' => 'Bot',
        'email' => 'bot@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'website' => 'https://spam.example',
    ])->assertSessionHasErrors('email');

    $this->post(route('register.store'), [
        'name' => 'Rápido',
        'email' => 'rapido@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'form_opened_at' => now()->getTimestamp(),
    ])->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(0);

    $this->post(route('register.store'), [
        'name' => 'Persona',
        'email' => 'persona@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'form_opened_at' => now()->subSeconds(config('avytra.registration.min_seconds_to_submit') + 1)->getTimestamp(),
    ])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'persona@example.com')->exists())->toBeTrue();
});

test('registration is rate limited per IP with the named register limiter', function () {
    $limit = (int) config('avytra.rate_limits.register_per_hour');

    // Invalid submissions count too: the limiter runs before validation.
    foreach (range(1, $limit) as $attempt) {
        $this->post(route('register.store'), ['email' => 'no-valido'])->assertSessionHasErrors();
    }

    $this->post(route('register.store'), [
        'name' => 'Persona',
        'email' => 'persona@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertTooManyRequests();

    expect(User::query()->count())->toBe(0);
});

test('the maintenance page is a self-contained brand page', function () {
    $html = view('errors.503')->render();

    expect($html)->toContain('AVYTRA')
        ->toContain(__('Back in a moment'))
        ->toContain('name="robots" content="noindex"')
        ->not->toContain('@vite')
        ->not->toContain('livewire');
});

test('the scheduler heartbeat is scheduled every minute and reports its health', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $event): bool => $event->description === 'avytra:scheduler-heartbeat');

    expect($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe('* * * * *');

    $heartbeat = app(SchedulerHeartbeat::class);

    expect($heartbeat->isHealthy())->toBeFalse()
        ->and($heartbeat->lastBeatAt())->toBeNull();

    $heartbeat->beat();

    expect($heartbeat->isHealthy())->toBeTrue();

    $this->travel(config('avytra.monitoring.scheduler_stale_minutes') + 1)->minutes();

    expect($heartbeat->isHealthy())->toBeFalse();
});

test('the operational summary warns when the scheduler is silent and when the superadmin has no 2FA', function () {
    $admin = User::factory()->superadmin()->create();
    Cache::forget(SchedulerHeartbeat::CACHE_KEY);

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertSee(__('The task scheduler is not running.'))
        ->assertSee(__('Protect the superadmin account with two-factor authentication.'))
        ->assertSee(route('security.edit'));

    app(SchedulerHeartbeat::class)->beat();
    $admin->forceFill(['two_factor_secret' => 'secret', 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertDontSee(__('The task scheduler is not running.'))
        ->assertDontSee(__('Protect the superadmin account with two-factor authentication.'));
});
