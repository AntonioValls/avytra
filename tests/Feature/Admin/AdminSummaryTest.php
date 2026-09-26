<?php

use App\Actions\Listings\ConfirmListingAvailability;
use App\Enums\ListingEventType;
use App\Models\Listing;
use App\Models\ListingReport;

test('the operational summary counts what needs attention and lists the listings involved', function () {
    $needing = Listing::factory()->needingConfirmation()->create(['title' => 'Necesita confirmar']);
    $expired = Listing::factory()->expired()->create(['title' => 'Pausada por el sistema']);
    $failed = Listing::factory()->published()->create(['title' => 'Aviso sin entregar']);
    $failed->events()->create(['type' => ListingEventType::ReminderFailed, 'payload' => ['stage' => 'first']]);
    Listing::factory()->published()->create(['title' => 'Todo en orden']);
    ListingReport::factory()->for(Listing::factory()->published())->create();

    actingAsSuperadmin();

    $this->get(route('admin.index'))
        ->assertOk()
        ->assertSee(__('Operational summary'))
        ->assertSee('Necesita confirmar')
        ->assertSee('Pausada por el sistema')
        ->assertSee('Aviso sin entregar')
        ->assertSee('Todo en orden')
        ->assertSee(route('admin.listings.index', ['condicion' => 'failed_reminder']))
        ->assertSee(route('admin.listings.index', ['condicion' => 'expired_recently']));

    expect($needing->needsConfirmation())->toBeTrue()
        ->and(Listing::query()->expiredRecently()->pluck('id')->all())->toBe([$expired->id])
        ->and(Listing::query()->withFailedReminder()->pluck('id')->all())->toBe([$failed->id]);
});

test('an undelivered reminder older than the last confirmation no longer counts as pending', function () {
    $listing = Listing::factory()->published(now()->subDays(50))->create();
    $listing->events()->create(['type' => ListingEventType::ReminderFailed, 'payload' => ['stage' => 'first'], 'created_at' => now()->subDays(3)]);

    expect(Listing::query()->withFailedReminder()->exists())->toBeTrue();

    $this->travel(1)->minutes();
    app(ConfirmListingAvailability::class)->handle($listing, $listing->owner());

    expect(Listing::query()->withFailedReminder()->exists())->toBeFalse()
        ->and($listing->fresh()->load('events')->latestFailedReminder())->toBeNull();
});
