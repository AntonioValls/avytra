<?php

use App\Enums\BusinessType;
use App\Enums\ContactMethod;
use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
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

/**
 * The "phone call" case end to end (docs/03): the superadmin creates the account, the
 * business and the listing, publishes and confirms on the person's behalf. No impersonation:
 * the person owns everything, the superadmin appears as creator/actor and every step is traceable.
 */
test('the superadmin assists a person from account creation to availability confirmation with full traceability', function () {
    Notification::fake();
    config()->set('avytra.support.email', 'soporte@avytra.test');
    $admin = actingAsSuperadmin();
    $municipality = Municipality::factory()->create();
    $category = Category::factory()->create();

    // 1. Account on behalf of a person without email.
    Livewire::test('pages::admin.users.index')
        ->call('openCreate')
        ->set('form.name', 'Antonia Ferrer')
        ->set('form.phone', '600123123')
        ->call('create')
        ->assertHasNoErrors();

    $person = User::where('name', 'Antonia Ferrer')->sole();

    expect($person->email)->toBe('soporte+antonia-ferrer@avytra.test')
        ->and($person->is_assisted)->toBeTrue();

    // 2. Business owned by the person, preselected from her admin page.
    $this->get(route('admin.businesses.create', ['propietario' => $person->id]))->assertOk();

    Livewire::withQueryParams(['propietario' => $person->id])
        ->test('pages::businesses.form', ['admin' => true])
        ->assertSet('ownerUserId', $person->id)
        ->set('form.business_type', BusinessType::Physical->value)
        ->set('form.category_id', $category->id)
        ->set('form.name', 'Mercería Ferrer')
        ->set('form.description', str_repeat('Mercería de barrio con clientela fiel. ', 8))
        ->set('location.province_id', $municipality->province_id)
        ->set('location.municipality_id', $municipality->id)
        ->call('save')
        ->assertHasNoErrors();

    $business = Business::sole();

    expect($business->owner_user_id)->toBe($person->id)
        ->and($business->created_by_user_id)->toBe($admin->id);

    // 3. Listing through the same wizard the person would use.
    Livewire::withQueryParams(['empresa' => $business->id])
        ->test('pages::listings.wizard', ['admin' => true])
        ->assertSet('ownerUserId', $person->id)
        ->set('operation.operation_types', ['transfer'])
        ->call('next')
        ->assertSet('step', 2)
        ->call('next')
        ->assertSet('step', 3)
        ->set('characteristics.reason_for_sale', 'Jubilación')
        ->call('next')
        ->assertSet('step', 4)
        ->set('economics.price_disclosure', PriceDisclosure::Exact->value)
        ->set('economics.asking_price', 30000)
        ->call('next')
        ->assertSet('step', 5)
        ->assertSet('location.municipality_id', $municipality->id)
        ->call('next')
        ->assertSet('step', 6)
        ->assertSet('contact.contact_name', '')
        ->set('contact.contact_name', 'Antonia')
        ->set('contact.preferred_contact_method', ContactMethod::Phone->value)
        ->set('contact.contact_phone', '600123123')
        ->call('next')
        ->assertSet('step', 7)
        ->call('next')
        ->assertSet('step', 8)
        ->call('useSuggestedTitle')
        ->call('publish')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.listings.index'));

    $listing = Listing::sole();

    expect($listing->status)->toBe(ListingStatus::Published)
        ->and($listing->created_by_user_id)->toBe($admin->id)
        ->and($listing->owner()->id)->toBe($person->id)
        ->and($listing->contact_phone)->toBe('600123123');

    Notification::assertSentTo($person, ListingPublished::class);

    // 4. Confirmation on her behalf, from the admin detail.
    $listing->forceFill(['last_confirmed_at' => now()->subDays(50)])->save();

    Livewire::test('pages::admin.listings.show', ['listing' => $listing->fresh()])->call('confirmOnBehalf');

    $listing->refresh();
    $confirmation = $listing->events()->where('type', ListingEventType::Confirmed)->sole();

    expect($listing->needsConfirmation())->toBeFalse()
        ->and($confirmation->actor_user_id)->toBe($admin->id)
        ->and($confirmation->on_behalf_of_user_id)->toBe($person->id)
        ->and($confirmation->payload['channel'])->toBe('admin');

    // 5. Every step is in the audit log on behalf of the person, and her page shows it all.
    $actions = AuditLog::where('on_behalf_of_user_id', $person->id)->where('actor_user_id', $admin->id)->pluck('action');

    expect($actions)->toContain('user.created_by_admin', 'business.created_by_admin', 'listing.created_by_admin', 'listing.published_by_admin', 'listing.confirmed_by_admin');

    $this->get(route('admin.users.show', $person))
        ->assertOk()
        ->assertSee('Mercería Ferrer')
        ->assertSee($listing->title)
        ->assertSee(__('Availability confirmed by the admin'));

    $this->get(route('admin.audit.index', ['usuario' => $person->id]))
        ->assertOk()
        ->assertSee(__('User created by the admin'))
        ->assertSee(__('Listing published by the admin'));
});

test('the assisted person can sign in and everything is hers, nothing belongs to the superadmin', function () {
    $admin = User::factory()->superadmin()->create();
    $person = User::factory()->assisted()->create();
    $business = Business::factory()->ownedBy($person)->create(['created_by_user_id' => $admin->id]);
    $listing = Listing::factory()->forBusiness($business)->published()->createdBy($admin)->create();

    $this->actingAs($person)
        ->get(route('panel.listings.index'))
        ->assertOk()
        ->assertSee($listing->title);

    $this->actingAs($admin)
        ->get(route('panel.listings.index'))
        ->assertOk()
        ->assertDontSee($listing->title);

    expect($person->can('update', $listing))->toBeTrue()
        ->and($person->can('update', $business))->toBeTrue();
});
