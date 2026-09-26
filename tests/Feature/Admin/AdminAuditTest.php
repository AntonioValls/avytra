<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use App\Support\Audit\AuditActions;
use Livewire\Livewire;

test('regular users receive a 404 for the audit log', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.audit.index'))->assertNotFound();
});

test('the superadmin sees every entry with actor, action, resource and affected user', function () {
    $admin = User::factory()->superadmin()->create(['name' => 'Admin Uno']);
    $owner = User::factory()->create(['name' => 'Propietaria Real']);
    $business = Business::factory()->ownedBy($owner)->create(['name' => 'Ferretería Norte']);

    AuditLog::factory()->create([
        'actor_user_id' => $admin->id,
        'on_behalf_of_user_id' => $owner->id,
        'action' => 'business.owner_changed',
        'subject_type' => $business->getMorphClass(),
        'subject_id' => $business->id,
        'changes' => ['before' => ['owner_user_id' => 1], 'after' => ['owner_user_id' => $owner->id]],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.audit.index'))
        ->assertOk()
        ->assertSee(AuditActions::label('business.owner_changed'))
        ->assertSee('Admin Uno')
        ->assertSee('Propietaria Real')
        ->assertSee('Ferretería Norte')
        ->assertSee(route('admin.businesses.edit', $business))
        ->assertSee('owner_user_id');
});

test('the log can be filtered by actor, action, resource type and involved user', function () {
    $first = User::factory()->superadmin()->create(['name' => 'Admin Primero']);
    $second = User::factory()->superadmin()->create(['name' => 'Admin Segundo']);
    $owner = User::factory()->create();
    $listing = Listing::factory()->published()->create(['title' => 'Traspaso bar auditado']);
    $created = User::factory()->assisted()->create(['name' => 'Creada Por Admin']);

    AuditLog::factory()->create(['actor_user_id' => $first->id, 'action' => 'listing.suspended', 'subject_type' => $listing->getMorphClass(), 'subject_id' => $listing->id, 'on_behalf_of_user_id' => $listing->owner()->id]);
    AuditLog::factory()->create(['actor_user_id' => $second->id, 'action' => 'user.created_by_admin', 'subject_type' => $created->getMorphClass(), 'subject_id' => $created->id, 'on_behalf_of_user_id' => $created->id]);
    AuditLog::factory()->create(['actor_user_id' => $second->id, 'action' => 'business.owner_changed', 'subject_type' => (new Business)->getMorphClass(), 'subject_id' => 999, 'on_behalf_of_user_id' => $owner->id]);

    $this->actingAs($first);

    Livewire::test('pages::admin.audit.index')
        ->set('actor', (string) $first->id)
        ->assertSee('Traspaso bar auditado')
        ->assertDontSee('Creada Por Admin')
        ->set('actor', '')
        ->set('action', 'user.created_by_admin')
        ->assertSee('Creada Por Admin')
        ->assertDontSee('Traspaso bar auditado')
        ->set('action', '')
        ->set('subjectType', 'business')
        ->assertSee(__('Deleted (#:id)', ['id' => 999]))
        ->assertDontSee('Traspaso bar auditado')
        ->set('subjectType', '')
        ->set('user', (string) $owner->id)
        ->assertSee(__('Showing only the entries that involve :name.', ['name' => $owner->name]))
        ->assertSee(AuditActions::label('business.owner_changed'))
        ->assertDontSee('Creada Por Admin');
});

test('the audit page has an empty state', function () {
    actingAsSuperadmin();

    $this->get(route('admin.audit.index'))->assertOk()->assertSee(__('No entries match.'));
});
