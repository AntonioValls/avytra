<?php

use App\Actions\Listings\ArchiveListing;
use App\Actions\Listings\ConfirmListingAvailability;
use App\Actions\Listings\ExpireListing;
use App\Actions\Listings\MarkListingAsSold;
use App\Actions\Listings\PauseListing;
use App\Actions\Listings\PublishListing;
use App\Actions\Listings\ResumeListing;
use App\Actions\Listings\SuspendListing;
use App\Actions\Listings\UnsuspendListing;
use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Exceptions\InvalidListingTransition;
use App\Exceptions\ListingNotPublishable;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingPublished;
use App\Notifications\ListingSuspended;
use Illuminate\Support\Facades\Notification;

/**
 * A draft whose business is complete enough to publish.
 */
function publishableDraft(): Listing
{
    $business = Business::factory()->physical()->withLocation()->create(['description' => str_repeat('Negocio consolidado. ', 12)]);

    return Listing::factory()->forBusiness($business)->draft()->create(['title' => 'Traspaso de panadería en Castellón']);
}

test('publishing a complete draft fixes the timestamps, the slug, records the event and notifies the owner once', function () {
    Notification::fake();
    $this->travelTo('2026-09-22 10:00:00');

    $listing = publishableDraft();
    $owner = $listing->owner();

    app(PublishListing::class)->handle($listing, $owner);

    expect($listing->status)->toBe(ListingStatus::Published)
        ->and($listing->published_at?->toDateTimeString())->toBe('2026-09-22 10:00:00')
        ->and($listing->last_confirmed_at?->toDateTimeString())->toBe('2026-09-22 10:00:00')
        ->and($listing->next_confirmation_at?->toDateTimeString())->toBe(now()->addDays(config('avytra.freshness.confirmation_period_days'))->toDateTimeString())
        ->and($listing->slug)->toBe('traspaso-de-panaderia-en-castellon')
        ->and($listing->events()->where('type', ListingEventType::Published)->count())->toBe(1)
        ->and(AuditLog::count())->toBe(0);

    Notification::assertSentTo($owner, ListingPublished::class);
});

test('the slug gets a numeric suffix when the title collides with another listing or an old URL', function () {
    Listing::factory()->create(['slug' => 'traspaso-de-panaderia-en-castellon']);
    $trashed = Listing::factory()->create(['slug' => 'traspaso-de-panaderia-en-castellon-2']);
    $trashed->delete();
    Listing::factory()->create()->slugRedirects()->create(['old_slug' => 'traspaso-de-panaderia-en-castellon-3']);

    $listing = publishableDraft();

    app(PublishListing::class)->handle($listing, $listing->owner());

    expect($listing->slug)->toBe('traspaso-de-panaderia-en-castellon-4');
});

test('publishing an incomplete draft fails with the list of missing data and changes nothing', function () {
    Notification::fake();
    $listing = Listing::factory()->bare()->create();

    try {
        app(PublishListing::class)->handle($listing, $listing->owner());
        $this->fail('Expected ListingNotPublishable.');
    } catch (ListingNotPublishable $exception) {
        expect($exception->report->fails())->toBeTrue()
            ->and($exception->report->messages())->not->toBeEmpty();
    }

    expect($listing->fresh()->status)->toBe(ListingStatus::Draft)
        ->and($listing->events()->count())->toBe(0);

    Notification::assertNothingSent();
});

test('publish only applies to drafts; paused and suspended listings return through resume and unsuspend', function (string $state) {
    $listing = Listing::factory()->{$state}()->create();

    app(PublishListing::class)->handle($listing, $listing->owner());
})->with(['paused', 'expired', 'suspended', 'sold'])->throws(InvalidListingTransition::class);

test('pausing a published listing records paused_at and the event', function () {
    $listing = Listing::factory()->published()->create();

    app(PauseListing::class)->handle($listing, $listing->owner());

    expect($listing->status)->toBe(ListingStatus::Paused)
        ->and($listing->paused_at)->not->toBeNull()
        ->and($listing->events()->sole()->type)->toBe(ListingEventType::Paused);
});

test('resuming a paused or expired listing restarts the freshness clock without touching published_at', function (string $state) {
    $this->travelTo('2026-09-22 10:00:00');
    $listing = Listing::factory()->{$state}()->create(['first_reminder_sent_at' => now()->subDays(3)]);
    $publishedAt = $listing->published_at;

    app(ResumeListing::class)->handle($listing, $listing->owner(), 'dashboard');

    expect($listing->status)->toBe(ListingStatus::Published)
        ->and($listing->published_at?->toDateTimeString())->toBe($publishedAt?->toDateTimeString())
        ->and($listing->last_confirmed_at?->toDateTimeString())->toBe('2026-09-22 10:00:00')
        ->and($listing->first_reminder_sent_at)->toBeNull()
        ->and($listing->paused_at)->toBeNull()
        ->and($listing->expired_at)->toBeNull()
        ->and($listing->events()->sole()->payload)->toBe(['channel' => 'dashboard']);
})->with(['paused', 'expired']);

test('confirming availability keeps the listing published and resets the reminder flags', function () {
    $this->travelTo('2026-09-22 10:00:00');
    $listing = Listing::factory()->needingConfirmation()->create(['first_reminder_sent_at' => now()->subDay()]);

    expect($listing->needsConfirmation())->toBeTrue();

    app(ConfirmListingAvailability::class)->handle($listing, $listing->owner(), 'dashboard');

    expect($listing->status)->toBe(ListingStatus::Published)
        ->and($listing->needsConfirmation())->toBeFalse()
        ->and($listing->last_confirmed_at?->toDateTimeString())->toBe('2026-09-22 10:00:00')
        ->and($listing->first_reminder_sent_at)->toBeNull()
        ->and($listing->events()->sole()->type)->toBe(ListingEventType::Confirmed);
});

test('a listing that is not published cannot be confirmed', function () {
    $listing = Listing::factory()->paused()->create();

    app(ConfirmListingAvailability::class)->handle($listing, $listing->owner());
})->throws(InvalidListingTransition::class);

test('marking as sold is terminal and frees the business for a new listing', function () {
    $listing = Listing::factory()->published()->create();

    app(MarkListingAsSold::class)->handle($listing, $listing->owner());

    expect($listing->status)->toBe(ListingStatus::Sold)
        ->and($listing->sold_at)->not->toBeNull()
        ->and($listing->business->openListing()->exists())->toBeFalse()
        ->and($listing->status->canTransitionTo(ListingStatus::Published))->toBeFalse();
});

test('archiving works from every non-terminal state and from sold', function (string $state) {
    $listing = Listing::factory()->{$state}()->create();

    app(ArchiveListing::class)->handle($listing, $listing->owner());

    expect($listing->status)->toBe(ListingStatus::Archived)
        ->and($listing->archived_at)->not->toBeNull()
        ->and(Listing::find($listing->id))->not->toBeNull();
})->with(['draft', 'published', 'paused', 'expired', 'sold', 'suspended']);

test('an archived listing cannot change again', function () {
    $listing = Listing::factory()->archived()->create();

    app(ArchiveListing::class)->handle($listing, $listing->owner());
})->throws(InvalidListingTransition::class);

test('the superadmin suspends a listing with a reason: audited, notified and hidden', function () {
    Notification::fake();
    $admin = User::factory()->superadmin()->create();
    $listing = Listing::factory()->published()->create();

    app(SuspendListing::class)->handle($listing, $admin, 'Datos falsos');

    expect($listing->status)->toBe(ListingStatus::Suspended)
        ->and($listing->suspension_reason)->toBe('Datos falsos')
        ->and($listing->isPubliclyVisible())->toBeFalse()
        ->and($listing->events()->sole()->on_behalf_of_user_id)->toBe($listing->owner()->id);

    $log = AuditLog::where('action', 'listing.suspended')->sole();

    expect($log->actor_user_id)->toBe($admin->id)
        ->and($log->on_behalf_of_user_id)->toBe($listing->owner()->id);

    Notification::assertSentTo($listing->owner(), ListingSuspended::class);
});

test('lifting a suspension republishes with a fresh availability period and clears the reason', function () {
    $admin = User::factory()->superadmin()->create();
    $listing = Listing::factory()->suspended()->create();

    app(UnsuspendListing::class)->handle($listing, $admin);

    expect($listing->status)->toBe(ListingStatus::Published)
        ->and($listing->suspension_reason)->toBeNull()
        ->and($listing->suspended_at)->toBeNull()
        ->and($listing->last_confirmed_at?->isToday())->toBeTrue()
        ->and(AuditLog::where('action', 'listing.unsuspended')->exists())->toBeTrue();
});

test('the system expires a published listing without an actor', function () {
    $listing = Listing::factory()->published(now()->subDays(61))->create();

    app(ExpireListing::class)->handle($listing);

    expect($listing->status)->toBe(ListingStatus::Expired)
        ->and($listing->expired_at)->not->toBeNull()
        ->and($listing->events()->sole()->actor_user_id)->toBeNull();
});

test('transitions performed by the superadmin on somebody else\'s listing are audited on behalf of the owner', function () {
    $admin = User::factory()->superadmin()->create();
    $listing = Listing::factory()->published()->create();

    app(PauseListing::class)->handle($listing, $admin);

    $log = AuditLog::where('action', 'listing.paused_by_admin')->sole();

    expect($log->actor_user_id)->toBe($admin->id)
        ->and($log->on_behalf_of_user_id)->toBe($listing->owner()->id)
        ->and($listing->events()->sole()->actor_user_id)->toBe($admin->id)
        ->and($listing->updated_by_user_id)->toBe($admin->id);
});

test('a sold listing stays publicly visible only during the configured window', function () {
    config()->set('avytra.freshness.sold_visible_days', 30);

    $recent = Listing::factory()->sold(now()->subDays(10))->create();
    $old = Listing::factory()->sold(now()->subDays(40))->create();

    expect($recent->isPubliclyVisible())->toBeTrue()
        ->and($old->isPubliclyVisible())->toBeFalse()
        ->and(Listing::publiclyVisible()->pluck('id')->all())->toBe([$recent->id]);
});
