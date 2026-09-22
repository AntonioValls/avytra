<?php

use App\Actions\Listings\ChangeListingSlug;
use App\Enums\ListingEventType;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\User;

test('changing the slug keeps the old one as a redirect, records the event and audits it', function () {
    $admin = User::factory()->superadmin()->create();
    $listing = Listing::factory()->published()->create(['slug' => 'antiguo']);

    app(ChangeListingSlug::class)->handle($listing, $admin, 'nuevo-slug');

    expect($listing->slug)->toBe('nuevo-slug')
        ->and($listing->slugRedirects()->sole()->old_slug)->toBe('antiguo')
        ->and($listing->events()->sole()->type)->toBe(ListingEventType::SlugChanged)
        ->and(AuditLog::where('action', 'listing.slug_changed')->exists())->toBeTrue();
});

test('a slug that another listing or an old URL already uses is rejected', function () {
    $admin = User::factory()->superadmin()->create();
    Listing::factory()->create(['slug' => 'ocupado']);
    Listing::factory()->create()->slugRedirects()->create(['old_slug' => 'antiguo-de-otro']);
    $listing = Listing::factory()->create();

    expect(fn () => app(ChangeListingSlug::class)->handle($listing, $admin, 'ocupado'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ChangeListingSlug::class)->handle($listing, $admin, 'antiguo-de-otro'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ChangeListingSlug::class)->handle($listing, $admin, 'Con Espacios'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(ChangeListingSlug::class)->handle($listing, $admin, 'categoria'))->toThrow(InvalidArgumentException::class);
});
