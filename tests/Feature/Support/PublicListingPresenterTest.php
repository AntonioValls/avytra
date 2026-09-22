<?php

use App\Enums\ContactMethod;
use App\Enums\Disclosure;
use App\Enums\FinancialMetric;
use App\Enums\LocationVisibility;
use App\Enums\OperationType;
use App\Enums\WebsiteVisibility;
use App\Models\Business;
use App\Models\Listing;
use App\Models\ListingFinancialMetric;
use App\Models\Location;
use App\Models\Municipality;
use App\Models\OnlineProfile;
use App\Models\Province;
use App\Support\Listings\PublicListingPresenter;
use Database\Factories\LocationFactory;

function physicalListingWith(Location|LocationFactory $location): Listing
{
    return Listing::factory()
        ->forBusiness(Business::factory()->physical()->withLocation($location)->create())
        ->published()
        ->create();
}

test('the location is projected according to its visibility', function (string $state, bool $municipalityShown, bool $addressShown) {
    $province = Province::factory()->create(['name' => 'Castellón']);
    $municipality = Municipality::factory()->create(['province_id' => $province->id, 'name' => 'Borriana']);

    $listing = physicalListingWith(Location::factory()->{$state}()->withCoordinates()->state([
        'province_id' => $province->id,
        'municipality_id' => $municipality->id,
        'address_line' => 'Camí d’Onda 14',
    ]));

    $presenter = PublicListingPresenter::for($listing);

    expect($presenter->provinceName())->toBe('Castellón')
        ->and($presenter->municipalityName())->toBe($municipalityShown ? 'Borriana' : null)
        ->and($presenter->addressLine())->toBe($addressShown ? 'Camí d’Onda 14' : null)
        ->and($presenter->locationText())->toBe($municipalityShown ? 'Borriana, Castellón' : 'Provincia de Castellón');

    $json = json_encode($presenter->offerJsonLd(), JSON_THROW_ON_ERROR);

    expect(str_contains($json, 'streetAddress'))->toBe($addressShown)
        ->and(str_contains($json, 'addressLocality'))->toBe($municipalityShown)
        ->and($json)->not->toContain('latitude');
})->with([
    'exact' => ['exact', true, true],
    'approximate' => ['approximate', true, false],
    'city only' => ['cityOnly', true, false],
    'hidden' => ['hidden', false, false],
]);

test('exact visibility without a real point falls back to the municipality and never prints the address', function () {
    $listing = physicalListingWith(Location::factory()->exact()->state(['address_line' => 'Calle Mayor 1']));

    $presenter = PublicListingPresenter::for($listing);

    expect($presenter->locationVisibility())->toBe(LocationVisibility::CityOnly)
        ->and($presenter->addressLine())->toBeNull();
});

test('the JSON-LD geo point comes from the derived public point, never from the private coordinates', function () {
    $listing = physicalListingWith(Location::factory()->exact()->withCoordinates(39.888888, -0.077777)->state([
        'public_latitude' => 39.9,
        'public_longitude' => -0.08,
        'public_radius_m' => null,
    ]));

    $json = json_encode(PublicListingPresenter::for($listing)->offerJsonLd(), JSON_THROW_ON_ERROR);

    expect($json)->toContain('39.9')->not->toContain('39.888888')->not->toContain('-0.077777');
});

test('online businesses have no location and read as "Online"', function () {
    $listing = Listing::factory()->forBusiness(Business::factory()->online()->withOnlineProfile()->create())->published()->create();

    $presenter = PublicListingPresenter::for($listing);

    expect($presenter->locationText())->toBe(__('Online'))
        ->and($presenter->provinceName())->toBeNull()
        ->and($presenter->onlineProfile())->not->toBeNull();
});

test('financial metrics honour their disclosure: hidden ones disappear, on request ones show no figure', function () {
    $listing = Listing::factory()->published()->create();
    ListingFinancialMetric::factory()->for($listing)->create(['metric' => FinancialMetric::AnnualRevenue, 'disclosure' => Disclosure::Exact, 'amount' => 240000]);
    ListingFinancialMetric::factory()->for($listing)->create(['metric' => FinancialMetric::AnnualProfit, 'disclosure' => Disclosure::Range, 'amount_min' => 30000, 'amount_max' => 40000]);
    ListingFinancialMetric::factory()->for($listing)->create(['metric' => FinancialMetric::Ebitda, 'disclosure' => Disclosure::Hidden, 'amount' => 38000]);
    ListingFinancialMetric::factory()->for($listing)->create(['metric' => FinancialMetric::StockValue, 'disclosure' => Disclosure::OnRequest, 'amount' => 12345]);

    $rows = collect(PublicListingPresenter::for($listing->fresh())->financialMetrics());

    expect($rows->pluck('metric'))->toEqual(collect([FinancialMetric::AnnualRevenue, FinancialMetric::AnnualProfit, FinancialMetric::StockValue]))
        ->and($rows->firstWhere('metric', FinancialMetric::AnnualRevenue)['text'])->toContain('240.000')
        ->and($rows->firstWhere('metric', FinancialMetric::AnnualProfit)['text'])->toContain('30.000')->toContain('40.000')
        ->and($rows->firstWhere('metric', FinancialMetric::StockValue)['text'])->toBe(__('On request'))
        ->and(json_encode($rows, JSON_THROW_ON_ERROR))->not->toContain('38000')->not->toContain('12345');
});

test('the price reads according to its disclosure and the JSON-LD only carries figures the seller disclosed', function () {
    $exact = PublicListingPresenter::for(Listing::factory()->published()->create(['asking_price' => 95000, 'is_price_negotiable' => true]));
    $range = PublicListingPresenter::for(Listing::factory()->published()->priceRange(100000, 150000)->create());
    $onRequest = PublicListingPresenter::for(Listing::factory()->published()->priceOnRequest()->create());

    expect($exact->priceText())->toContain('95.000')
        ->and($exact->isPriceNegotiable())->toBeTrue()
        ->and($exact->offerJsonLd()['price'])->toBe(95000)
        ->and($range->priceText())->toContain('100.000')->toContain('150.000')
        ->and($range->offerJsonLd()['priceSpecification']['minPrice'])->toBe(100000)
        ->and($onRequest->priceText())->toBe(__('On request'))
        ->and($onRequest->offerJsonLd())->not->toHaveKey('price')->not->toHaveKey('priceSpecification');
});

test('legal form and website appear only when the seller made them public', function () {
    $private = Listing::factory()->forBusiness(Business::factory()->create([
        'legal_name' => 'Sociedad Privada, S.L.',
        'show_legal_form' => false,
        'website_url' => 'https://secreta.example',
        'website_visibility' => WebsiteVisibility::Private,
    ]))->published()->create();

    $public = Listing::factory()->forBusiness(Business::factory()->create([
        'show_legal_form' => true,
        'website_url' => 'https://publica.example',
        'website_visibility' => WebsiteVisibility::Public,
    ]))->published()->create();

    expect(PublicListingPresenter::for($private)->legalForm())->toBeNull()
        ->and(PublicListingPresenter::for($private)->websiteUrl())->toBeNull()
        ->and(PublicListingPresenter::for($public)->legalForm())->not->toBeNull()
        ->and(PublicListingPresenter::for($public)->websiteUrl())->toBe('https://publica.example');
});

test('social profiles follow the website visibility', function () {
    $business = Business::factory()->online()->withOnlineProfile(OnlineProfile::factory()->state([
        'social_profiles' => [['network' => 'Instagram', 'url' => 'https://instagram.com/x']],
    ]))->create(['website_url' => 'https://x.example', 'website_visibility' => WebsiteVisibility::Private]);
    $listing = Listing::factory()->forBusiness($business)->published()->create();

    expect(PublicListingPresenter::for($listing)->onlineProfile()['social_profiles'])->toBe([]);

    $business->update(['website_visibility' => WebsiteVisibility::Public]);

    expect(PublicListingPresenter::for($listing->fresh())->onlineProfile()['social_profiles'])->toHaveCount(1);
});

test('phone, WhatsApp and email are sensitive channels; website, form and free text are not', function () {
    $listing = Listing::factory()->published()->create([
        'preferred_contact_method' => ContactMethod::Phone,
        'contact_phone' => '+34600111222',
        'contact_email' => 'ventas@example.com',
        'contact_website_url' => 'https://tienda.example',
        'contact_whatsapp' => null,
    ]);

    $presenter = PublicListingPresenter::for($listing);

    expect($presenter->contactMethods())->toBe([ContactMethod::Phone, ContactMethod::Email, ContactMethod::Website])
        ->and($presenter->publicChannel(ContactMethod::Phone))->toBeNull()
        ->and($presenter->publicChannel(ContactMethod::Website))->toBe('https://tienda.example')
        ->and($presenter->sensitiveChannel(ContactMethod::Phone))->toBe('+34600111222')
        ->and($presenter->sensitiveChannel(ContactMethod::Website))->toBeNull();
});

test('operation types list the primary one first and the stake only for partial operations', function () {
    $listing = Listing::factory()->offering([OperationType::FullSale, OperationType::PartnerEntry], OperationType::PartnerEntry)->published()->create(['stake_percent' => 40]);

    $presenter = PublicListingPresenter::for($listing);

    expect($presenter->operationTypes())->toBe([OperationType::PartnerEntry, OperationType::FullSale])
        ->and($presenter->stakePercent())->toBe(40);

    $full = Listing::factory()->offering([OperationType::FullSale])->published()->create(['stake_percent' => 40]);

    expect(PublicListingPresenter::for($full)->stakePercent())->toBeNull();
});

test('the freshness text counts days and flags listings pending renewal', function () {
    expect(PublicListingPresenter::for(Listing::factory()->published()->create())->freshnessText())->toBe(__('Availability confirmed today'))
        ->and(PublicListingPresenter::for(Listing::factory()->published(now()->subDay())->create())->freshnessText())->toBe(__('Availability confirmed yesterday'))
        ->and(PublicListingPresenter::for(Listing::factory()->published(now()->subDays(8))->create())->freshnessText())->toBe(__('Availability confirmed :days days ago', ['days' => 8]));

    $pending = PublicListingPresenter::for(Listing::factory()->needingConfirmation()->create());

    expect($pending->isPendingRenewal())->toBeTrue()
        ->and($pending->freshnessText())->toContain(__('pending renewal'));

    expect(PublicListingPresenter::for(Listing::factory()->sold()->create())->freshnessText())->toBeNull();
});

test('the metadata robots value follows the public state of the listing', function () {
    expect(PublicListingPresenter::for(Listing::factory()->published()->create())->robots())->toBeNull()
        ->and(PublicListingPresenter::for(Listing::factory()->sold()->create())->robots())->toBeNull()
        ->and(PublicListingPresenter::for(Listing::factory()->sold(now()->subDays(config('avytra.freshness.sold_visible_days') + 1))->create())->robots())->toBe('noindex,follow')
        ->and(PublicListingPresenter::for(Listing::factory()->paused()->create())->robots())->toBe('noindex,follow');
});

test('the wizard preview may override the title without touching the listing', function () {
    $listing = Listing::factory()->create(['title' => 'Guardado']);

    $presenter = PublicListingPresenter::for($listing)->withTitle('Escribiendo');

    expect($presenter->title())->toBe('Escribiendo')
        ->and($listing->fresh()->title)->toBe('Guardado');
});
