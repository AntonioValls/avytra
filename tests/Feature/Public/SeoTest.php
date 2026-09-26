<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\CategorySeeder;

test('robots.txt is served by a route with the private areas and the sitemap under app.url', function () {
    config()->set('app.url', 'https://avytra.test');

    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *')
        ->assertSee('Disallow: /panel')
        ->assertSee('Disallow: /admin')
        ->assertSee('Disallow: /login')
        ->assertSee('Sitemap: https://avytra.test/sitemap.xml');
});

test('a sector or a province in the explore query string redirects permanently to its own page keeping the other filters', function () {
    $category = Category::factory()->create(['slug' => 'hosteleria']);
    $province = Province::factory()->create();

    $this->get(route('listings.index', ['sector' => 'hosteleria']))
        ->assertRedirect(route('categories.show', $category))
        ->assertStatus(301);

    $this->get(route('listings.index', ['provincia' => $province->slug, 'tipo' => 'fisico']))
        ->assertRedirect(route('provinces.show', $province).'?tipo=fisico')
        ->assertStatus(301);

    // Both at once have no page of their own: the canonical handles it.
    $this->get(route('listings.index', ['sector' => 'hosteleria', 'provincia' => $province->slug]))->assertOk();

    // An unknown sector is just a filter with no results.
    $this->get(route('listings.index', ['sector' => 'no-existe']))->assertOk();
});

test('category and province pages carry an ItemList and a BreadcrumbList only when they have content', function () {
    $category = Category::factory()->create(['slug' => 'comercio', 'name' => 'Comercio']);
    $business = Business::factory()->withLocation()->create(['category_id' => $category->id]);
    $listing = Listing::factory()->forBusiness($business)->published()->create(['title' => 'Tienda de barrio en venta']);
    $province = Province::query()->findOrFail($business->location->province_id);
    $empty = Category::factory()->create(['slug' => 'vacia']);

    $response = $this->get(route('categories.show', $category))
        ->assertOk()
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"url":"'.route('listings.show', $listing->slug).'"', false)
        ->assertSee('"numberOfItems":1', false);

    $html = (string) $response->getContent();
    $body = substr($html, (int) strpos($html, '<body'));

    expect(substr_count($body, '"@type":"ItemList"'))->toBe(1)
        ->and(substr_count(substr($html, 0, (int) strpos($html, '<body')), 'application/ld+json'))->toBe(0);

    $this->get(route('provinces.show', $province))
        ->assertOk()
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('Sectores: comercio', false);

    $this->get(route('categories.show', $empty))
        ->assertOk()
        ->assertDontSee('"@type":"ItemList"', false)
        ->assertSee('name="robots" content="noindex,follow"', false);
});

test('the home page describes the site and the organisation for search engines', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('"@type":"WebSite"', false)
        ->assertSee('"@type":"SearchAction"', false)
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"logo":"'.asset('app-icon-512.png').'"', false);
});

test('the panel, the admin and the auth screens are never indexed', function () {
    $this->get(route('login'))->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    $this->actingAs(User::factory()->superadmin()->create())->get(route('admin.index'))->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('public pages expose Open Graph and Twitter metadata with the image size', function () {
    $listing = Listing::factory()->published()->create();

    $this->get(route('listings.show', $listing->slug))
        ->assertOk()
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<meta property="og:image:width" content="1200">', false)
        ->assertSee('<meta property="og:image:height" content="630">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<meta name="twitter:image" content="'.asset('og-default.png').'">', false);

    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee('<meta name="description" content="'.__('Privacy policy of AVYTRA: which data we store, what is never public and how to exercise your rights.').'">', false);
});

test('sectors seeded for the marketplace carry a real introduction', function () {
    $this->seed(CategorySeeder::class);

    $category = Category::query()->roots()->where('slug', 'hosteleria-y-restauracion')->firstOrFail();

    expect($category->description)->toContain('Restaurantes');

    $this->get(route('categories.show', $category))->assertOk()->assertSee('Restaurantes, bares, cafeterías');
});
