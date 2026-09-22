<?php

use App\Enums\BusinessType;
use App\Enums\ContactMethod;
use App\Enums\Disclosure;
use App\Enums\FinancialMetric;
use App\Enums\ListingStatus;
use App\Enums\OnlineBusinessType;
use App\Enums\OperationType;
use App\Enums\PriceDisclosure;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\User;
use App\Notifications\ListingPublished;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('the wizard page renders for a registered user and redirects guests', function () {
    $this->get(route('listings.create'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('listings.create'))
        ->assertOk()
        ->assertSee(__('New listing'))
        ->assertSee(__('A new business'));
});

test('step 1 requires a business, a type and at least one operation', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::listings.wizard')
        ->call('next')
        ->assertHasErrors(['operation.operation_types'])
        ->assertSet('step', 1)
        ->set('selectedBusinessId', 0)
        ->set('operation.operation_types', ['transfer'])
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', 2);

    expect(Listing::count())->toBe(0);
});

test('choosing an existing business creates the draft immediately with the operations chosen', function () {
    $business = Business::factory()->create();
    actingAsOwnerOf($business);

    Livewire::test('pages::listings.wizard')
        ->set('selectedBusinessId', $business->id)
        ->set('operation.operation_types', ['full_sale', 'partner_entry'])
        ->set('operation.primary_operation_type', 'partner_entry')
        ->set('operation.stake_percent', 40)
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', 2);

    $listing = Listing::sole();

    expect($listing->business_id)->toBe($business->id)
        ->and($listing->status)->toBe(ListingStatus::Draft)
        ->and($listing->primary_operation_type)->toBe(OperationType::PartnerEntry)
        ->and($listing->stake_percent)->toBe(40)
        ->and($listing->offeredOperationTypes())->toBe([OperationType::FullSale, OperationType::PartnerEntry]);
});

test('a business that already has an open listing cannot be chosen', function () {
    $business = Business::factory()->create();
    Listing::factory()->forBusiness($business)->published()->create();
    actingAsOwnerOf($business);

    Livewire::test('pages::listings.wizard')
        ->assertDontSee($business->name)
        ->set('selectedBusinessId', $business->id)
        ->set('operation.operation_types', ['transfer'])
        ->call('next')
        ->assertHasErrors(['selectedBusinessId'])
        ->assertSet('step', 1);
});

test('the primary operation must be one of the offered ones and a single operation becomes primary on its own', function () {
    $business = Business::factory()->create();
    actingAsOwnerOf($business);

    $component = Livewire::test('pages::listings.wizard')
        ->set('selectedBusinessId', $business->id)
        ->set('operation.operation_types', ['transfer', 'asset_sale'])
        ->set('operation.primary_operation_type', 'full_sale')
        ->call('next')
        ->assertHasErrors(['operation.primary_operation_type']);

    $component
        ->set('operation.operation_types', ['asset_sale'])
        ->call('next')
        ->assertHasNoErrors();

    expect(Listing::sole()->primary_operation_type)->toBe(OperationType::AssetSale);
});

test('a new business is created together with the draft when step 2 is completed', function () {
    $user = User::factory()->create();
    $sector = Category::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::listings.wizard')
        ->set('selectedBusinessId', 0)
        ->set('business.business_type', BusinessType::Online->value)
        ->set('operation.operation_types', ['full_sale'])
        ->call('next')
        ->assertSet('step', 2)
        ->set('business.name', 'Tienda de té online')
        ->set('business.category_id', $sector->id)
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', 3);

    $business = Business::sole();
    $listing = Listing::sole();

    expect($business->owner_user_id)->toBe($user->id)
        ->and($business->business_type)->toBe(BusinessType::Online)
        ->and($listing->business_id)->toBe($business->id)
        ->and($listing->created_by_user_id)->toBe($user->id);
});

test('each step persists its data so the owner can leave and continue later', function () {
    $municipality = Municipality::factory()->create();
    $business = Business::factory()->physical()->create();
    $user = actingAsOwnerOf($business);

    $component = Livewire::test('pages::listings.wizard')
        ->set('selectedBusinessId', $business->id)
        ->set('operation.operation_types', ['transfer'])
        ->call('next')
        ->set('business.description', str_repeat('Negocio en marcha. ', 12))
        ->call('next')
        ->assertSet('step', 3)
        ->set('characteristics.reason_for_sale', 'Jubilación')
        ->set('characteristics.highlights', ['Clientela fiel', ''])
        ->set('characteristics.includes_equipment', 'yes')
        ->set('characteristics.premises_is_rented', 'yes')
        ->call('next')
        ->assertSet('step', 4)
        ->set('economics.price_disclosure', PriceDisclosure::Exact->value)
        ->set('economics.asking_price', 85000)
        ->set('economics.is_price_negotiable', true)
        ->set('economics.metrics.annual_revenue', ['disclosure' => Disclosure::Range->value, 'amount' => null, 'amount_min' => 100000, 'amount_max' => 150000])
        ->set('economics.metrics.monthly_rent', ['disclosure' => Disclosure::Exact->value, 'amount' => 900, 'amount_min' => null, 'amount_max' => null])
        ->call('next')
        ->assertSet('step', 5)
        ->set('location.province_id', $municipality->province_id)
        ->set('location.municipality_id', $municipality->id)
        ->call('next')
        ->assertSet('step', 6)
        ->assertSet('contact.contact_name', $user->name)
        ->assertSet('contact.contact_email', $user->email)
        ->set('contact.preferred_contact_method', ContactMethod::Phone->value)
        ->set('contact.contact_phone', '+34600000000')
        ->set('contact.whatsapp_same_as_phone', true)
        ->call('next')
        ->assertSet('step', 7)
        ->call('saveAndExit')
        ->assertRedirect(route('listings.index'));

    $listing = Listing::sole()->load('financialMetrics');

    expect($listing->reason_for_sale)->toBe('Jubilación')
        ->and($listing->highlights)->toBe(['Clientela fiel'])
        ->and($listing->includes_equipment)->toBeTrue()
        ->and($listing->includes_stock)->toBeNull()
        ->and($listing->premises_is_rented)->toBeTrue()
        ->and($listing->asking_price)->toBe(85000)
        ->and($listing->is_price_negotiable)->toBeTrue()
        ->and($listing->financialMetrics->firstWhere('metric', FinancialMetric::AnnualRevenue)?->amount_max)->toBe(150000)
        ->and($listing->financialMetrics->firstWhere('metric', FinancialMetric::MonthlyRent)?->amount)->toBe(900)
        ->and($listing->financialMetrics)->toHaveCount(2)
        ->and($listing->contact_phone)->toBe('+34600000000')
        ->and($listing->contact_whatsapp)->toBe('+34600000000')
        ->and($listing->contact_name)->toBe($user->name)
        ->and($listing->business->fresh()->location->municipality_id)->toBe($municipality->id)
        ->and($listing->business->fresh()->description)->toStartWith('Negocio en marcha.');

    // Coming back opens the draft with everything in place, at any step.
    Livewire::withQueryParams(['paso' => 4])
        ->test('pages::listings.wizard', ['listing' => $listing])
        ->assertSet('step', 4)
        ->assertSet('economics.asking_price', 85000)
        ->assertSet('economics.metrics.monthly_rent.amount', 900)
        ->assertSet('contact.contact_phone', '+34600000000');
});

test('the price step validates coherent figures', function () {
    $listing = Listing::factory()->bare()->create();
    actingAsOwnerOf($listing->business);

    Livewire::withQueryParams(['paso' => 4])
        ->test('pages::listings.wizard', ['listing' => $listing])
        ->set('economics.price_disclosure', PriceDisclosure::Exact->value)
        ->call('next')
        ->assertHasErrors(['economics.asking_price' => 'required'])
        ->set('economics.price_disclosure', PriceDisclosure::Range->value)
        ->set('economics.asking_price_min', 500)
        ->set('economics.asking_price_max', 100)
        ->call('next')
        ->assertHasErrors(['economics.asking_price_max'])
        ->assertSet('step', 4);
});

test('the contact step requires the channel of the preferred method', function () {
    $listing = Listing::factory()->bare()->create();
    actingAsOwnerOf($listing->business);

    Livewire::withQueryParams(['paso' => 6])
        ->test('pages::listings.wizard', ['listing' => $listing])
        ->set('contact.contact_email', '')
        ->set('contact.preferred_contact_method', ContactMethod::Whatsapp->value)
        ->call('next')
        ->assertHasErrors(['contact.contact_whatsapp' => 'required'])
        ->set('contact.whatsapp_same_as_phone', true)
        ->set('contact.contact_phone', '+34600000000')
        ->call('next')
        ->assertHasNoErrors();

    expect($listing->fresh()->contact_whatsapp)->toBe('+34600000000');
});

test('the last step shows what is missing and refuses to publish until it is complete', function () {
    Notification::fake();
    $listing = Listing::factory()->bare()->create();
    actingAsOwnerOf($listing->business);

    Livewire::withQueryParams(['paso' => 8])
        ->test('pages::listings.wizard', ['listing' => $listing])
        ->assertSee(__('What is still missing'))
        ->assertSee(__('Choose how buyers should contact you.'))
        ->call('publish')
        ->assertNotSet('publishErrors', []);

    expect($listing->fresh()->status)->toBe(ListingStatus::Draft);

    Notification::assertNothingSent();
});

test('a complete draft is published from the last step with the suggested title', function () {
    Notification::fake();
    $business = Business::factory()->physical()->withLocation()->create(['description' => str_repeat('Negocio consolidado. ', 12)]);
    $listing = Listing::factory()->forBusiness($business)->create(['title' => null, 'slug' => null]);
    actingAsOwnerOf($business);

    Livewire::withQueryParams(['paso' => 8])
        ->test('pages::listings.wizard', ['listing' => $listing])
        ->assertSee(__('Everything is in order. You can publish now.'))
        ->call('useSuggestedTitle')
        ->call('publish')
        ->assertHasNoErrors()
        ->assertRedirect(route('listings.index'));

    $listing->refresh();

    expect($listing->status)->toBe(ListingStatus::Published)
        ->and($listing->title)->not->toBeNull()
        ->and($listing->slug)->not->toBeNull();

    Notification::assertSentTo($business->owner, ListingPublished::class);
});

test('editing a published listing keeps it published and offers save instead of publish', function () {
    $listing = Listing::factory()->published()->create(['reason_for_sale' => 'Antes']);
    actingAsOwnerOf($listing->business);

    Livewire::withQueryParams(['paso' => 3])
        ->test('pages::listings.wizard', ['listing' => $listing])
        ->set('characteristics.reason_for_sale', 'Después')
        ->call('goTo', 8)
        ->assertSet('step', 8)
        ->assertSee(__('Save changes'))
        ->assertDontSee('wire:click="publish"', false);

    expect($listing->fresh()->reason_for_sale)->toBe('Después')
        ->and($listing->fresh()->status)->toBe(ListingStatus::Published);
});

test('another user cannot open somebody else\'s draft and the owner cannot open a suspended one', function () {
    $draft = Listing::factory()->create();
    $suspended = Listing::factory()->suspended()->create();

    $this->actingAs(User::factory()->create());
    Livewire::test('pages::listings.wizard', ['listing' => $draft])->assertForbidden();

    $this->actingAs($suspended->owner());
    Livewire::test('pages::listings.wizard', ['listing' => $suspended])->assertForbidden();
});

test('the superadmin creates a listing for another user from the admin wizard and it is audited', function () {
    $admin = actingAsSuperadmin();
    $owner = User::factory()->create();
    $business = Business::factory()->ownedBy($owner)->online()->withOnlineProfile()->create();

    $this->get(route('admin.listings.create'))->assertOk()->assertSee(__('Choose a user'));

    Livewire::test('pages::listings.wizard', ['admin' => true])
        ->set('ownerUserId', $owner->id)
        ->set('selectedBusinessId', $business->id)
        ->set('operation.operation_types', ['full_sale'])
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->assertSet('contact.contact_name', '');

    $listing = Listing::sole();

    expect($listing->business_id)->toBe($business->id)
        ->and($listing->created_by_user_id)->toBe($admin->id)
        ->and($listing->owner()->id)->toBe($owner->id)
        ->and(AuditLog::where('action', 'listing.created_by_admin')->where('on_behalf_of_user_id', $owner->id)->exists())->toBeTrue();
});

test('the business card links to the wizard with the business preselected', function () {
    $business = Business::factory()->online()->withOnlineProfile()->create();
    actingAsOwnerOf($business);

    $this->get(route('businesses.index'))
        ->assertOk()
        ->assertSee(route('listings.create', ['empresa' => $business->id]));

    Livewire::withQueryParams(['empresa' => $business->id])
        ->test('pages::listings.wizard')
        ->assertSet('selectedBusinessId', $business->id)
        ->assertSet('business.name', $business->name)
        ->assertSet('business.business_type', BusinessType::Online->value);
});

test('an online business skips the premises and asks for the online profile in step 5', function () {
    $listing = Listing::factory()->bare()->create();
    $listing->business->update(['business_type' => BusinessType::Online]);
    actingAsOwnerOf($listing->business);

    Livewire::withQueryParams(['paso' => 5])
        ->test('pages::listings.wizard', ['listing' => $listing])
        ->assertSee(__('Online business type'))
        ->assertDontSee(__('Province'))
        ->call('next')
        ->assertHasErrors(['online.online_business_type' => 'required'])
        ->set('online.online_business_type', OnlineBusinessType::Saas->value)
        ->call('next')
        ->assertHasNoErrors();

    expect($listing->business->fresh()->onlineProfile->online_business_type)->toBe(OnlineBusinessType::Saas);
});
