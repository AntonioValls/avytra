<?php

use App\Actions\Listings\ConfirmListingAvailability;
use App\Actions\Listings\ResumeListing;
use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Enums\ReminderStage;
use App\Models\Listing;
use App\Notifications\ListingExpired;
use App\Notifications\ListingFreshnessReminder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

test('the sequence first reminder, second reminder and automatic pause happens exactly once', function () {
    $listing = Listing::factory()->published()->create();
    $owner = $listing->owner();

    $this->travel(config('avytra.freshness.first_reminder_days') - 1)->days();
    $this->artisan('avytra:listings:process-freshness')->assertSuccessful();
    Notification::assertNothingSent();

    $this->travel(1)->days();
    $this->artisan('avytra:listings:process-freshness')->assertSuccessful();
    $this->artisan('avytra:listings:process-freshness')->assertSuccessful();

    Notification::assertSentTo($owner, ListingFreshnessReminder::class, fn (ListingFreshnessReminder $notification) => $notification->stage === ReminderStage::First && $notification->listing->is($listing));
    Notification::assertSentTimes(ListingFreshnessReminder::class, 1);
    expect($listing->fresh()->first_reminder_sent_at)->not->toBeNull()
        ->and($listing->fresh()->second_reminder_sent_at)->toBeNull()
        ->and($listing->fresh()->status)->toBe(ListingStatus::Published);

    $this->travel(config('avytra.freshness.second_reminder_days') - config('avytra.freshness.first_reminder_days'))->days();
    $this->artisan('avytra:listings:process-freshness')->assertSuccessful();
    $this->artisan('avytra:listings:process-freshness')->assertSuccessful();

    Notification::assertSentTo($owner, ListingFreshnessReminder::class, fn (ListingFreshnessReminder $notification) => $notification->stage === ReminderStage::Second);
    Notification::assertSentTimes(ListingFreshnessReminder::class, 2);
    expect($listing->fresh()->second_reminder_sent_at)->not->toBeNull()
        ->and($listing->fresh()->status)->toBe(ListingStatus::Published);

    $this->travel(config('avytra.freshness.confirmation_period_days') - config('avytra.freshness.second_reminder_days'))->days();
    $this->artisan('avytra:listings:process-freshness')->assertSuccessful();
    $this->artisan('avytra:listings:process-freshness')->assertSuccessful();

    Notification::assertSentTo($owner, ListingExpired::class);
    Notification::assertSentTimes(ListingExpired::class, 1);
    Notification::assertSentTimes(ListingFreshnessReminder::class, 2);

    $listing->refresh();

    expect($listing->status)->toBe(ListingStatus::Expired)
        ->and($listing->expired_at)->not->toBeNull()
        ->and($listing->events()->where('type', ListingEventType::ReminderSent)->count())->toBe(2)
        ->and($listing->events()->where('type', ListingEventType::Expired)->count())->toBe(1);
});

test('a dry run lists what would happen without sending or changing anything', function () {
    $listing = Listing::factory()->published(now()->subDays(config('avytra.freshness.first_reminder_days')))->create(['title' => 'Traspaso panadería centro']);
    $due = Listing::factory()->published(now()->subDays(config('avytra.freshness.confirmation_period_days')))->create();

    $this->artisan('avytra:listings:process-freshness', ['--dry-run' => true])
        ->expectsOutputToContain('Traspaso panadería centro')
        ->expectsOutputToContain('Would process: first reminders 1, second reminders 0, paused 1.')
        ->assertSuccessful();

    Notification::assertNothingSent();

    expect($listing->fresh()->first_reminder_sent_at)->toBeNull()
        ->and($due->fresh()->status)->toBe(ListingStatus::Published);
});

test('a listing overdue for both reminders receives only the second one', function () {
    $listing = Listing::factory()->published(now()->subDays(config('avytra.freshness.second_reminder_days') + 1))->create();

    $this->artisan('avytra:listings:process-freshness')->assertSuccessful();

    Notification::assertSentTimes(ListingFreshnessReminder::class, 1);
    Notification::assertSentTo($listing->owner(), ListingFreshnessReminder::class, fn (ListingFreshnessReminder $notification) => $notification->stage === ReminderStage::Second);

    expect($listing->fresh()->first_reminder_sent_at)->not->toBeNull()
        ->and($listing->fresh()->second_reminder_sent_at)->not->toBeNull();
});

test('a listing overdue for the pause is paused without reminders when the scheduler was down', function () {
    $listing = Listing::factory()->published(now()->subDays(config('avytra.freshness.confirmation_period_days') + 3))->create();

    $this->artisan('avytra:listings:process-freshness')->assertSuccessful();

    Notification::assertNotSentTo($listing->owner(), ListingFreshnessReminder::class);
    Notification::assertSentTo($listing->owner(), ListingExpired::class);

    expect($listing->fresh()->status)->toBe(ListingStatus::Expired);
});

test('confirming resets the flags so the sequence starts again from day zero', function () {
    $listing = Listing::factory()->published()->create();

    $this->travel(config('avytra.freshness.first_reminder_days'))->days();
    $this->artisan('avytra:listings:process-freshness')->assertSuccessful();
    Notification::assertSentTimes(ListingFreshnessReminder::class, 1);

    app(ConfirmListingAvailability::class)->handle($listing->fresh(), $listing->owner());

    expect($listing->fresh()->first_reminder_sent_at)->toBeNull()
        ->and($listing->fresh()->needsConfirmation())->toBeFalse();

    $this->travel(config('avytra.freshness.first_reminder_days') - 1)->days();
    $this->artisan('avytra:listings:process-freshness')->assertSuccessful();
    Notification::assertSentTimes(ListingFreshnessReminder::class, 1);

    $this->travel(1)->days();
    $this->artisan('avytra:listings:process-freshness')->assertSuccessful();
    Notification::assertSentTimes(ListingFreshnessReminder::class, 2);
});

test('listings that are not published are never touched', function () {
    $paused = Listing::factory()->paused()->create();
    $draft = Listing::factory()->draft()->create();
    $sold = Listing::factory()->sold()->create();

    $this->travel(config('avytra.freshness.confirmation_period_days') + 10)->days();
    $this->artisan('avytra:listings:process-freshness')->assertSuccessful();

    Notification::assertNothingSent();

    expect($paused->fresh()->status)->toBe(ListingStatus::Paused)
        ->and($draft->fresh()->status)->toBe(ListingStatus::Draft)
        ->and($sold->fresh()->status)->toBe(ListingStatus::Sold);
});

test('an expired listing leaves the public marketplace and comes back when resumed', function () {
    $listing = Listing::factory()->published(now()->subDays(config('avytra.freshness.confirmation_period_days')))->create();

    $this->get(route('listings.show', $listing->slug))->assertOk();

    $this->artisan('avytra:listings:process-freshness')->assertSuccessful();

    $this->get(route('listings.show', $listing->slug))->assertNotFound();

    app(ResumeListing::class)->handle($listing->fresh(), $listing->owner(), 'dashboard');

    $this->get(route('listings.show', $listing->slug))->assertOk();
});
