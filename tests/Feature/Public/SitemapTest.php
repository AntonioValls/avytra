<?php

use App\Actions\Listings\ChangeListingSlug;
use App\Actions\Listings\PauseListing;
use App\Models\Business;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Province;
use App\Models\User;
use App\Support\Listings\MarketplaceAggregates;
use App\Support\Seo\Sitemap;
use Illuminate\Support\Facades\Cache;

test('the sitemap lists the visible listings, the sectors and provinces with content and the static pages', function () {
    $category = Category::factory()->create(['slug' => 'hosteleria']);
    $emptyCategory = Category::factory()->create(['slug' => 'vacia']);
    $business = Business::factory()->withLocation()->create(['category_id' => $category->id]);
    $province = Province::query()->findOrFail($business->location->province_id);

    $published = Listing::factory()->forBusiness($business)->published()->create(['slug' => 'traspaso-bar-centro']);
    $sold = Listing::factory()->sold(now()->subDays(3))->create(['slug' => 'vendida-reciente']);
    $oldSold = Listing::factory()->sold(now()->subDays(config('avytra.freshness.sold_visible_days') + 1))->create(['slug' => 'vendida-antigua']);
    $paused = Listing::factory()->paused()->create(['slug' => 'pausada']);
    $draft = Listing::factory()->draft()->create(['slug' => 'borrador']);

    $response = $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('home').'</loc>', false)
        ->assertSee('<loc>'.route('listings.index').'</loc>', false)
        ->assertSee('<loc>'.route('publish.landing').'</loc>', false)
        ->assertSee('<loc>'.route('legal.privacy').'</loc>', false)
        ->assertSee('<loc>'.route('categories.show', 'hosteleria').'</loc>', false)
        ->assertSee('<loc>'.route('provinces.show', $province).'</loc>', false)
        ->assertSee('<loc>'.route('listings.show', 'traspaso-bar-centro').'</loc>', false)
        ->assertSee('<loc>'.route('listings.show', 'vendida-reciente').'</loc>', false)
        ->assertSee('<lastmod>'.$sold->sold_at->toAtomString().'</lastmod>', false)
        ->assertDontSee('vacia')
        ->assertDontSee('vendida-antigua')
        ->assertDontSee('pausada')
        ->assertDontSee('borrador')
        ->assertDontSee(route('listings.online'));

    $xml = (string) $response->getContent();

    expect(substr_count($xml, route('listings.show', 'traspaso-bar-centro')))->toBe(1)
        ->and(substr_count($xml, '<url>'))->toBe(substr_count($xml, '<loc>'))
        ->and($xml)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>');
});

test('the online page enters the sitemap only when an online business is published', function () {
    $this->get(route('sitemap'))->assertDontSee(route('listings.online'));

    Listing::factory()->forBusiness(Business::factory()->online()->withOnlineProfile()->create())->published()->create();
    app(Sitemap::class)->forget();
    app(MarketplaceAggregates::class)->forget();

    $this->get(route('sitemap'))->assertSee('<loc>'.route('listings.online').'</loc>', false);
});

test('the sitemap is cached and forgotten when a listing changes status', function () {
    $listing = Listing::factory()->published()->create(['slug' => 'se-pausa']);

    $this->get(route('sitemap'))->assertSee('se-pausa');

    expect(Cache::has(Sitemap::CACHE_KEY))->toBeTrue();

    app(PauseListing::class)->handle($listing, $listing->owner());

    expect(Cache::has(Sitemap::CACHE_KEY))->toBeFalse();

    $this->get(route('sitemap'))->assertDontSee('se-pausa');
});

test('the sitemap is forgotten when a listing URL changes', function () {
    $listing = Listing::factory()->published()->create(['slug' => 'antigua-url']);
    $admin = User::factory()->superadmin()->create();

    $this->get(route('sitemap'))->assertSee('antigua-url');

    app(ChangeListingSlug::class)->handle($listing, $admin, 'nueva-url');

    $this->get(route('sitemap'))->assertSee('nueva-url')->assertDontSee('antigua-url');
});
