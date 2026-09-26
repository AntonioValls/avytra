<?php

use App\Enums\OperationType;
use App\Models\Business;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Location;
use App\Models\Province;
use Livewire\Livewire;

test('explore lists only publicly visible listings with their count', function () {
    Listing::factory()->published()->create(['title' => 'Visible publicada']);
    Listing::factory()->sold()->create(['title' => 'Visible vendida']);
    Listing::factory()->paused()->create(['title' => 'Oculta pausada']);
    Listing::factory()->draft()->create(['title' => 'Oculto borrador']);

    $this->get(route('listings.index'))
        ->assertOk()
        ->assertSee('Visible publicada')
        ->assertSee('Visible vendida')
        ->assertDontSee('Oculta pausada')
        ->assertDontSee('Oculto borrador')
        ->assertSee('2 empresas');
});

test('the text filter matches the title and the business name', function () {
    Listing::factory()->published()->create(['title' => 'Traspaso de panadería']);
    Listing::factory()->forBusiness(Business::factory()->create(['name' => 'Ferretería Central']))->published()->create(['title' => 'Venta de negocio']);
    Listing::factory()->published()->create(['title' => 'Otra cosa']);

    Livewire::withQueryParams(['q' => 'panadería'])->test('pages::public.listings.index')
        ->assertSee('Traspaso de panadería')
        ->assertDontSee('Otra cosa')
        ->set('search', 'Ferretería')
        ->assertSee('Venta de negocio')
        ->assertDontSee('Traspaso de panadería');
});

test('sector, type, operation and province filters narrow the results', function () {
    $sector = Category::factory()->create(['slug' => 'hosteleria']);
    $province = Province::factory()->create(['slug' => 'castellon']);

    $target = Listing::factory()
        ->forBusiness(Business::factory()->hybrid()->withLocation(Location::factory()->state(['province_id' => $province->id]))->create(['category_id' => $sector->id]))
        ->offering([OperationType::Transfer, OperationType::PartnerEntry])
        ->published()
        ->create(['title' => 'Objetivo']);
    Listing::factory()->published()->create(['title' => 'Ruido']);

    Livewire::test('pages::public.listings.index')
        ->set('sector', 'hosteleria')->assertSee('Objetivo')->assertDontSee('Ruido')->set('sector', '')
        ->set('type', 'hibrido')->assertSee('Objetivo')->assertDontSee('Ruido')->set('type', 'online')->assertDontSee('Objetivo')->set('type', '')
        ->set('operation', OperationType::PartnerEntry->value)->assertSee('Objetivo')->assertDontSee('Ruido')->set('operation', '')
        ->set('province', 'castellon')->assertSee('Objetivo')->assertDontSee('Ruido');

    expect($target->fresh())->not->toBeNull();
});

test('price filters compare against the exact price or the range and leave "on request" out', function () {
    Listing::factory()->published()->create(['title' => 'Barata', 'asking_price' => 50000]);
    Listing::factory()->published()->create(['title' => 'Cara', 'asking_price' => 500000]);
    Listing::factory()->published()->priceRange(90000, 200000)->create(['title' => 'Rango']);
    Listing::factory()->published()->priceOnRequest()->create(['title' => 'Consultar']);

    Livewire::test('pages::public.listings.index')
        ->set('priceMin', '100000')
        ->assertSee('Cara')->assertSee('Rango')->assertDontSee('Barata')->assertDontSee('Consultar')
        ->set('priceMin', '')
        ->set('priceMax', '100000')
        ->assertSee('Barata')->assertSee('Rango')->assertDontSee('Cara')->assertDontSee('Consultar');
});

test('results can be sorted by price with priced listings first', function () {
    Listing::factory()->published(now()->subDays(3))->create(['title' => 'Media', 'asking_price' => 100000]);
    Listing::factory()->published(now()->subDays(2))->create(['title' => 'Barata', 'asking_price' => 10000]);
    Listing::factory()->published(now()->subDay())->create(['title' => 'Consultar'])->update(['price_disclosure' => 'on_request', 'asking_price' => null]);

    $ascending = Livewire::test('pages::public.listings.index')->set('sort', 'precio_asc')->html();
    $descending = Livewire::test('pages::public.listings.index')->set('sort', 'precio_desc')->html();

    expect(strpos($ascending, 'Barata'))->toBeLessThan(strpos($ascending, 'Media'))
        ->and(strpos($ascending, 'Media'))->toBeLessThan(strpos($ascending, 'Consultar'))
        ->and(strpos($descending, 'Media'))->toBeLessThan(strpos($descending, 'Barata'))
        ->and(strpos($descending, 'Barata'))->toBeLessThan(strpos($descending, 'Consultar'));
});

test('results are paginated', function () {
    config(['avytra.pagination.public_cards' => 2]);
    Listing::factory()->count(3)->published()->create();

    $firstPage = Livewire::test('pages::public.listings.index');
    $secondPage = Livewire::withQueryParams(['page' => 2])->test('pages::public.listings.index');

    expect(substr_count($firstPage->html(), 'wire:key="card-'))->toBe(2)
        ->and(substr_count($secondPage->html(), 'wire:key="card-'))->toBe(1);

    $this->get(route('listings.index', ['page' => 2]))->assertOk()->assertSee('3 empresas');
});

test('the empty state offers to clear filters', function () {
    Listing::factory()->published()->create(['title' => 'Única']);

    Livewire::test('pages::public.listings.index')
        ->set('search', 'nada-que-ver')
        ->assertSee(__('No businesses match your search.'))
        ->assertSee(__('Clear filters'))
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSee('Única');
});

test('category and province pages fix their filter and carry their own title', function () {
    $sector = Category::factory()->create(['name' => 'Hostelería', 'slug' => 'hosteleria']);
    $province = Province::factory()->create(['name' => 'Castellón', 'slug' => 'castellon']);
    Listing::factory()
        ->forBusiness(Business::factory()->withLocation(Location::factory()->state(['province_id' => $province->id]))->create(['category_id' => $sector->id]))
        ->published()
        ->create(['title' => 'Bar en Castellón']);
    Listing::factory()->published()->create(['title' => 'Fuera de sector']);

    $this->get(route('categories.show', $sector))
        ->assertOk()
        ->assertSee(__(':sector for sale and transfer', ['sector' => 'Hostelería']))
        ->assertSee('Bar en Castellón')
        ->assertDontSee('Fuera de sector')
        ->assertDontSee('name="robots"', false)
        ->assertSee('<link rel="canonical" href="'.route('categories.show', $sector).'">', false);

    $this->get(route('provinces.show', $province))
        ->assertOk()
        ->assertSee(__('Businesses for sale in :province', ['province' => 'Castellón']))
        ->assertSee('Bar en Castellón')
        ->assertDontSee('Fuera de sector');
});

test('empty category, province and online pages are noindex', function () {
    $sector = Category::factory()->create();
    $province = Province::factory()->create();

    $this->get(route('categories.show', $sector))->assertOk()->assertSee('noindex,follow', false);
    $this->get(route('provinces.show', $province))->assertOk()->assertSee('noindex,follow', false);
    $this->get(route('listings.online'))->assertOk()->assertSee('noindex,follow', false);
});

test('the online page shows only online businesses', function () {
    Listing::factory()->forBusiness(Business::factory()->online()->withOnlineProfile()->create())->published()->create(['title' => 'Tienda online']);
    Listing::factory()->published()->create(['title' => 'Local físico']);

    $this->get(route('listings.online'))
        ->assertOk()
        ->assertSee('Tienda online')
        ->assertDontSee('Local físico')
        ->assertDontSee('name="robots"', false);
});

test('inactive or child categories have no public page', function () {
    $inactive = Category::factory()->inactive()->create();
    $child = Category::factory()->childOf()->create();

    $this->get(route('categories.show', $inactive))->assertNotFound();
    $this->get(route('categories.show', $child))->assertNotFound();
    $this->get('/empresas/categoria/no-existe')->assertNotFound();
});

test('a sector chosen on explore leads to the category page and free-text filters keep the clean canonical', function () {
    $sector = Category::factory()->create(['slug' => 'hosteleria']);

    // A full page load with ?sector= is redirected permanently (docs/15); the canonical covers the in-page filter change.
    $this->get(route('listings.index', ['sector' => 'hosteleria']))
        ->assertStatus(301)
        ->assertRedirect(route('categories.show', $sector));

    $this->get(route('listings.index', ['q' => 'bar']))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('listings.index').'">', false);
});
