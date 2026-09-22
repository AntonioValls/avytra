<?php

use App\Enums\BusinessType;
use App\Enums\OnlineBusinessType;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Livewire\Livewire;

test('regular users receive a 404 for the admin business pages', function (string $route) {
    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertNotFound();
})->with(['admin.businesses.index', 'admin.businesses.create']);

test('the superadmin sees every business with its owner', function () {
    $first = Business::factory()->withLocation()->create(['name' => 'Panadería Sol']);
    $second = Business::factory()->online()->create(['name' => 'Tienda online']);

    actingAsSuperadmin();

    $this->get(route('admin.businesses.index'))
        ->assertOk()
        ->assertSee('Panadería Sol')
        ->assertSee('Tienda online')
        ->assertSee($first->owner->email)
        ->assertSee($second->owner->email)
        ->assertSee(route('admin.businesses.edit', $first));
});

test('the table can be filtered by search text and by business type', function () {
    $owner = User::factory()->create(['email' => 'maria@example.com']);
    $physical = Business::factory()->ownedBy($owner)->physical()->create(['name' => 'Bar Pepe']);
    $online = Business::factory()->online()->create(['name' => 'App de recetas']);

    actingAsSuperadmin();

    Livewire::test('pages::admin.businesses.index')
        ->set('search', 'maria@')
        ->assertSee($physical->name)
        ->assertDontSee($online->name)
        ->set('search', '')
        ->set('type', BusinessType::Online->value)
        ->assertSee($online->name)
        ->assertDontSee($physical->name);
});

test('the superadmin creates a business for another user and stays recorded as its creator', function () {
    $admin = actingAsSuperadmin();
    $owner = User::factory()->create();

    $this->get(route('admin.businesses.create'))
        ->assertOk()
        ->assertSee(__('Choose a user'));

    Livewire::test('pages::businesses.form', ['admin' => true])
        ->set('ownerUserId', $owner->id)
        ->set('form.business_type', BusinessType::Online->value)
        ->set('form.category_id', Category::factory()->create()->id)
        ->set('form.name', 'Creada por el administrador')
        ->set('online.online_business_type', OnlineBusinessType::Content->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.businesses.index'));

    $business = Business::sole();

    expect($business->owner_user_id)->toBe($owner->id)
        ->and($business->created_by_user_id)->toBe($admin->id)
        ->and(AuditLog::where('action', 'business.created_by_admin')->where('on_behalf_of_user_id', $owner->id)->exists())->toBeTrue();
});

test('the admin form requires an existing owner', function () {
    actingAsSuperadmin();

    Livewire::test('pages::businesses.form', ['admin' => true])
        ->set('ownerUserId', null)
        ->set('form.business_type', BusinessType::Online->value)
        ->set('form.category_id', Category::factory()->create()->id)
        ->set('form.name', 'Sin propietario')
        ->set('online.online_business_type', OnlineBusinessType::Content->value)
        ->call('save')
        ->assertHasErrors(['ownerUserId' => 'required']);

    expect(Business::count())->toBe(0);
});

test('the superadmin edits somebody else\'s business from the admin and the edit is audited', function () {
    $admin = actingAsSuperadmin();
    $business = Business::factory()->online()->withOnlineProfile()->create(['name' => 'Before']);

    Livewire::test('pages::businesses.form', ['business' => $business, 'admin' => true])
        ->assertSet('ownerUserId', $business->owner_user_id)
        ->set('form.name', 'After')
        ->call('save')
        ->assertHasNoErrors();

    expect($business->fresh()->name)->toBe('After')
        ->and($business->fresh()->owner_user_id)->toBe($business->owner_user_id)
        ->and($business->fresh()->updated_by_user_id)->toBe($admin->id)
        ->and(AuditLog::where('action', 'business.updated_by_admin')->exists())->toBeTrue();
});

test('the superadmin transfers a business to another user with an audit entry', function () {
    $admin = actingAsSuperadmin();
    $business = Business::factory()->create();
    $newOwner = User::factory()->create(['name' => 'Nuevo Propietario']);

    Livewire::test('pages::admin.businesses.index')
        ->call('openTransfer', $business->id)
        ->assertSet('transferBusinessId', $business->id)
        ->set('ownerSearch', 'Nuevo')
        ->assertSee('Nuevo Propietario')
        ->set('newOwnerId', $newOwner->id)
        ->call('transfer')
        ->assertHasNoErrors();

    expect($business->fresh()->owner_user_id)->toBe($newOwner->id);

    $log = AuditLog::where('action', 'business.owner_changed')->sole();

    expect($log->actor_user_id)->toBe($admin->id)
        ->and($log->on_behalf_of_user_id)->toBe($newOwner->id);
});

test('a transfer needs an existing user', function () {
    actingAsSuperadmin();
    $business = Business::factory()->create();

    Livewire::test('pages::admin.businesses.index')
        ->call('openTransfer', $business->id)
        ->set('newOwnerId', 999999)
        ->call('transfer')
        ->assertHasErrors(['newOwnerId']);

    expect($business->fresh()->owner_user_id)->toBe($business->owner_user_id);
});

test('a regular user cannot transfer ownership even through the component', function () {
    $business = Business::factory()->create();
    actingAsOwnerOf($business);

    Livewire::test('pages::admin.businesses.index')
        ->call('openTransfer', $business->id)
        ->assertForbidden();
});
