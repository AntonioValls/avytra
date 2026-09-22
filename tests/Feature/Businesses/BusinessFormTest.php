<?php

use App\Enums\BusinessType;
use App\Enums\LocationVisibility;
use App\Enums\OnlineBusinessType;
use App\Models\Business;
use App\Models\Category;
use App\Models\Location;
use App\Models\Municipality;
use App\Models\OnlineProfile;
use App\Models\Province;
use App\Models\User;
use Livewire\Livewire;

test('the create page renders for a registered user', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('businesses.create'))
        ->assertOk()
        ->assertSee(__('New business'))
        ->assertDontSee(__('Choose a user'));
});

test('a user creates a physical business with its premises', function () {
    $user = User::factory()->create();
    $sector = Category::factory()->create();
    $subsector = Category::factory()->childOf($sector)->create();
    $municipality = Municipality::factory()->withPopulation(3000)->create(['latitude' => 39.98, 'longitude' => -0.05]);

    $this->actingAs($user);

    Livewire::test('pages::businesses.form')
        ->set('form.business_type', BusinessType::Physical->value)
        ->set('form.category_id', $sector->id)
        ->set('form.subcategory_id', $subsector->id)
        ->set('form.name', 'Panadería Sol')
        ->set('form.description', 'Panadería tradicional en el centro.')
        ->set('location.province_id', $municipality->province_id)
        ->set('location.municipality_id', $municipality->id)
        ->set('location.address_line', 'Calle Mayor 1')
        ->set('location.location_visibility', LocationVisibility::Approximate->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('businesses.index'));

    $business = Business::sole();

    expect($business->owner_user_id)->toBe($user->id)
        ->and($business->created_by_user_id)->toBe($user->id)
        ->and($business->subcategory_id)->toBe($subsector->id)
        ->and($business->location->municipality_id)->toBe($municipality->id)
        ->and($business->location->address_line)->toBe('Calle Mayor 1')
        ->and($business->location->public_latitude)->toBe(39.98)
        ->and($business->location->public_radius_m)->toBe(1500)
        ->and($business->onlineProfile)->toBeNull();
});

test('a user creates an online business without premises', function () {
    $user = User::factory()->create();
    $sector = Category::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::businesses.form')
        ->set('form.business_type', BusinessType::Online->value)
        ->set('form.category_id', $sector->id)
        ->set('form.name', 'Tienda online de té')
        ->set('form.website_url', 'https://example.com')
        ->set('online.online_business_type', OnlineBusinessType::Ecommerce->value)
        ->set('online.monthly_visits', 20000)
        ->set('online.acquisition_channels', ['seo', 'social_ads'])
        ->set('online.sells_on_marketplaces', 'Amazon, Etsy')
        ->set('online.social_profiles', [['network' => 'Instagram', 'url' => 'https://instagram.com/te'], ['network' => '', 'url' => '']])
        ->call('save')
        ->assertHasNoErrors();

    $business = Business::sole();

    expect($business->location)->toBeNull()
        ->and($business->website_url)->toBe('https://example.com')
        ->and($business->onlineProfile->online_business_type)->toBe(OnlineBusinessType::Ecommerce)
        ->and($business->onlineProfile->acquisition_channels)->toBe(['seo', 'social_ads'])
        ->and($business->onlineProfile->sells_on_marketplaces)->toBe(['Amazon', 'Etsy'])
        ->and($business->onlineProfile->social_profiles)->toBe([['network' => 'Instagram', 'url' => 'https://instagram.com/te']]);
});

test('a user creates a hybrid business with premises and an online profile', function () {
    $user = User::factory()->create();
    $municipality = Municipality::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::businesses.form')
        ->set('form.business_type', BusinessType::Hybrid->value)
        ->set('form.category_id', Category::factory()->create()->id)
        ->set('form.name', 'Academia con campus online')
        ->set('location.province_id', $municipality->province_id)
        ->set('location.municipality_id', $municipality->id)
        ->set('online.online_business_type', OnlineBusinessType::Service->value)
        ->call('save')
        ->assertHasNoErrors();

    $business = Business::sole();

    expect($business->location)->not->toBeNull()
        ->and($business->onlineProfile)->not->toBeNull();
});

test('a physical business requires a province and a municipality but no online data', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::businesses.form')
        ->set('form.business_type', BusinessType::Physical->value)
        ->set('form.category_id', Category::factory()->create()->id)
        ->set('form.name', 'Bar')
        ->call('save')
        ->assertHasErrors(['location.province_id' => 'required', 'location.municipality_id' => 'required'])
        ->assertHasNoErrors(['online.online_business_type']);

    expect(Business::count())->toBe(0);
});

test('an online business requires the online business type but no location', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::businesses.form')
        ->set('form.business_type', BusinessType::Online->value)
        ->set('form.category_id', Category::factory()->create()->id)
        ->set('form.name', 'SaaS')
        ->call('save')
        ->assertHasErrors(['online.online_business_type' => 'required'])
        ->assertHasNoErrors(['location.province_id']);
});

test('the municipality must belong to the chosen province', function () {
    $this->actingAs(User::factory()->create());
    $municipality = Municipality::factory()->create();
    $otherProvince = Province::factory()->create();

    Livewire::test('pages::businesses.form')
        ->set('form.business_type', BusinessType::Physical->value)
        ->set('form.category_id', Category::factory()->create()->id)
        ->set('form.name', 'Bar')
        ->set('location.province_id', $otherProvince->id)
        ->set('location.municipality_id', $municipality->id)
        ->call('save')
        ->assertHasErrors(['location.municipality_id']);
});

test('the subsector must belong to the chosen sector and the sector must be a root category', function () {
    $this->actingAs(User::factory()->create());
    $sector = Category::factory()->create();
    $foreignSubsector = Category::factory()->childOf()->create();

    Livewire::test('pages::businesses.form')
        ->set('form.business_type', BusinessType::Online->value)
        ->set('form.category_id', $foreignSubsector->id)
        ->set('form.subcategory_id', $foreignSubsector->id)
        ->set('form.name', 'SaaS')
        ->set('online.online_business_type', OnlineBusinessType::Saas->value)
        ->call('save')
        ->assertHasErrors(['form.category_id', 'form.subcategory_id']);

    Livewire::test('pages::businesses.form')
        ->set('form.category_id', $sector->id)
        ->set('form.subcategory_id', $foreignSubsector->id)
        ->call('save')
        ->assertHasErrors(['form.subcategory_id']);
});

test('changing the sector clears the subsector', function () {
    $this->actingAs(User::factory()->create());
    $sector = Category::factory()->create();
    $subsector = Category::factory()->childOf($sector)->create();

    Livewire::test('pages::businesses.form')
        ->set('form.category_id', $sector->id)
        ->set('form.subcategory_id', $subsector->id)
        ->set('form.category_id', Category::factory()->create()->id)
        ->assertSet('form.subcategory_id', null);
});

test('the website must be a valid http or https URL and the founding year cannot be in the future', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::businesses.form')
        ->set('form.website_url', 'ftp://example.com')
        ->set('form.founded_year', now()->year + 1)
        ->call('save')
        ->assertHasErrors(['form.website_url', 'form.founded_year']);
});

test('a regular user cannot pick another owner: the business is always theirs', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::businesses.form')
        ->set('ownerUserId', $other->id)
        ->set('form.business_type', BusinessType::Online->value)
        ->set('form.category_id', Category::factory()->create()->id)
        ->set('form.name', 'Mine')
        ->set('online.online_business_type', OnlineBusinessType::App->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(Business::sole()->owner_user_id)->toBe($user->id);
});

test('the owner opens the edit page with the current data and can update it', function () {
    $business = Business::factory()->physical()->withLocation()->create(['name' => 'Before']);
    actingAsOwnerOf($business);

    $this->get(route('businesses.edit', $business))
        ->assertOk()
        ->assertSee(__('Edit business'))
        ->assertSee('Before');

    Livewire::test('pages::businesses.form', ['business' => $business])
        ->assertSet('form.name', 'Before')
        ->assertSet('location.municipality_id', $business->location->municipality_id)
        ->set('form.name', 'After')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('businesses.index'));

    expect($business->fresh()->name)->toBe('After')
        ->and($business->fresh()->updated_by_user_id)->toBe($business->owner->id);
});

test('changing a physical business to online removes its premises', function () {
    $business = Business::factory()->physical()->withLocation()->create();
    actingAsOwnerOf($business);

    Livewire::test('pages::businesses.form', ['business' => $business])
        ->set('form.business_type', BusinessType::Online->value)
        ->set('online.online_business_type', OnlineBusinessType::Saas->value)
        ->call('save')
        ->assertHasNoErrors();

    expect(Location::where('business_id', $business->id)->exists())->toBeFalse()
        ->and(OnlineProfile::where('business_id', $business->id)->exists())->toBeTrue();
});

test('another user cannot open or save somebody else\'s business', function () {
    $business = Business::factory()->create(['name' => 'Before']);
    $this->actingAs(User::factory()->create());

    $this->get(route('businesses.edit', $business))->assertForbidden();

    Livewire::test('pages::businesses.form', ['business' => $business])
        ->assertForbidden();

    expect($business->fresh()->name)->toBe('Before');
});

test('guests are redirected to the login page', function () {
    $this->get(route('businesses.create'))->assertRedirect(route('login'));
});
