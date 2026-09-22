<?php

use App\Actions\Reports\SubmitListingReport;
use App\Enums\ListingReportReason;
use App\Enums\ListingReportStatus;
use App\Enums\ListingStatus;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\User;
use App\Notifications\ListingReportReceived;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

test('a visitor reports a listing with a reason and an email, and the superadmin is notified', function () {
    Notification::fake();
    $superadmin = User::factory()->superadmin()->create();
    $listing = Listing::factory()->published()->create();

    Livewire::test('public.report-listing', ['listingId' => $listing->id])
        ->set('reason', ListingReportReason::Scam->value)
        ->call('submit')
        ->assertHasErrors(['email']);

    // The component can only be "opened" through mount; the Locked timestamp is set there.
    $component = Livewire::test('public.report-listing', ['listingId' => $listing->id]);
    $this->travel(10)->seconds();

    $component
        ->set('reason', ListingReportReason::Scam->value)
        ->set('message', 'Piden una señal por adelantado.')
        ->set('email', 'visitante@example.com')
        ->call('submit')
        ->assertHasNoErrors();

    $report = ListingReport::query()->sole();

    expect($report->listing_id)->toBe($listing->id)
        ->and($report->reason)->toBe(ListingReportReason::Scam)
        ->and($report->reporter_email)->toBe('visitante@example.com')
        ->and($report->reporter_user_id)->toBeNull()
        ->and($report->status)->toBe(ListingReportStatus::Open)
        ->and($report->ip_hash)->not->toBeNull()->not->toContain('127.0.0.1');

    Notification::assertSentTo($superadmin, ListingReportReceived::class);
});

test('a logged-in reporter is linked by account and needs no email', function () {
    Notification::fake();
    $user = User::factory()->create();
    $listing = Listing::factory()->published()->create();

    $this->actingAs($user);
    $component = Livewire::test('public.report-listing', ['listingId' => $listing->id]);
    $this->travel(10)->seconds();

    $component->set('reason', ListingReportReason::Duplicate->value)->call('submit')->assertHasNoErrors();

    $report = ListingReport::query()->sole();

    expect($report->reporter_user_id)->toBe($user->id)
        ->and($report->reporter_email)->toBeNull();
});

test('bots that fill the honeypot or submit too fast are silently ignored', function () {
    Notification::fake();
    $listing = Listing::factory()->published()->create();

    Livewire::test('public.report-listing', ['listingId' => $listing->id])
        ->set('reason', ListingReportReason::Other->value)
        ->set('email', 'bot@example.com')
        ->call('submit')
        ->assertHasNoErrors();

    $component = Livewire::test('public.report-listing', ['listingId' => $listing->id]);
    $this->travel(10)->seconds();

    $component
        ->set('reason', ListingReportReason::Other->value)
        ->set('email', 'bot@example.com')
        ->set('website', 'https://spam.example')
        ->call('submit')
        ->assertHasNoErrors();

    expect(ListingReport::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('the same visitor cannot open a second report on the same listing', function () {
    Notification::fake();
    $listing = Listing::factory()->published()->create();
    ListingReport::factory()->forListing($listing)->create(['ip_hash' => SubmitListingReport::hashIp('127.0.0.1')]);

    $component = Livewire::test('public.report-listing', ['listingId' => $listing->id]);
    $this->travel(10)->seconds();

    $component
        ->set('reason', ListingReportReason::Scam->value)
        ->set('email', 'otra@example.com')
        ->call('submit')
        ->assertHasErrors(['reason']);

    expect(ListingReport::query()->count())->toBe(1);
});

test('reports are rate limited per IP', function () {
    Notification::fake();
    $listing = Listing::factory()->published()->create();

    for ($i = 0; $i < (int) config('avytra.reports.rate_limit_per_hour'); $i++) {
        RateLimiter::hit('report:127.0.0.1', 3600);
    }

    $component = Livewire::test('public.report-listing', ['listingId' => $listing->id]);
    $this->travel(10)->seconds();

    $component
        ->set('reason', ListingReportReason::Scam->value)
        ->set('email', 'otra@example.com')
        ->call('submit')
        ->assertHasErrors(['reason']);

    expect(ListingReport::query()->count())->toBe(0);
});

test('a listing that is not public cannot be reported', function () {
    $listing = Listing::factory()->paused()->create();

    $component = Livewire::test('public.report-listing', ['listingId' => $listing->id]);
    $this->travel(10)->seconds();

    $component->set('reason', ListingReportReason::Scam->value)->set('email', 'x@example.com')->call('submit')->assertNotFound();
});

test('regular users receive a 404 for the reports inbox', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.reports.index'))->assertNotFound();
});

test('the superadmin resolves a report with a quick action on the listing and the decision is audited', function () {
    Notification::fake();
    $admin = actingAsSuperadmin();
    $report = ListingReport::factory()->create(['message' => 'Ya no está en venta']);

    $this->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee($report->listing->title)
        ->assertSee('Ya no está en venta');

    Livewire::test('pages::admin.reports.index')
        ->call('openResolve', $report->id)
        ->set('quickAction', 'pause')
        ->set('notes', 'Comprobado por teléfono.')
        ->call('resolve')
        ->assertHasNoErrors();

    $report->refresh();

    expect($report->status)->toBe(ListingReportStatus::Resolved)
        ->and($report->resolved_by_user_id)->toBe($admin->id)
        ->and($report->resolution_notes)->toBe('Comprobado por teléfono.')
        ->and($report->listing->status)->toBe(ListingStatus::Paused)
        ->and(AuditLog::query()->where('action', 'listing_report.resolved')->where('actor_user_id', $admin->id)->exists())->toBeTrue();
});

test('the superadmin dismisses a report and it leaves the open inbox', function () {
    actingAsSuperadmin();
    $report = ListingReport::factory()->create();

    Livewire::test('pages::admin.reports.index')
        ->assertSee($report->listing->title)
        ->call('dismiss', $report->id)
        ->assertDontSee($report->listing->title);

    expect($report->fresh()->status)->toBe(ListingReportStatus::Dismissed)
        ->and(AuditLog::query()->where('action', 'listing_report.dismissed')->exists())->toBeTrue();
});
