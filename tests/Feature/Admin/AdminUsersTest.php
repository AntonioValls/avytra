<?php

use App\Actions\Users\CreateAssistedUser;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\SetPasswordInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('regular users receive a 404 for the admin user pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.users.index'))->assertNotFound();
    $this->actingAs($user)->get(route('admin.users.show', $user))->assertNotFound();
});

test('the superadmin lists every account and filters by search and by assisted accounts', function () {
    $regular = User::factory()->create(['name' => 'Marta Vidal', 'email' => 'marta@example.com']);
    $assisted = User::factory()->assisted()->create(['name' => 'Pepe Asistido', 'phone' => '600111222']);

    actingAsSuperadmin();

    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Marta Vidal')
        ->assertSee('Pepe Asistido')
        ->assertSee(route('admin.users.show', $regular));

    Livewire::test('pages::admin.users.index')
        ->set('search', '600111')
        ->assertSee('Pepe Asistido')
        ->assertDontSee('Marta Vidal')
        ->set('search', '')
        ->set('condition', 'assisted')
        ->assertSee('Pepe Asistido')
        ->assertDontSee('Marta Vidal');
});

test('the superadmin creates a verified assisted account with a random password and an invitation to set it', function () {
    Notification::fake();
    $admin = actingAsSuperadmin();

    Livewire::test('pages::admin.users.index')
        ->call('openCreate')
        ->set('form.name', 'Carmen Ruiz')
        ->set('form.email', 'carmen@example.com')
        ->set('form.phone', '+34 600 000 000')
        ->set('form.send_password_link', true)
        ->call('create')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.users.show', User::where('email', 'carmen@example.com')->sole()));

    $user = User::where('email', 'carmen@example.com')->sole();

    expect($user->is_assisted)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->phone)->toBe('+34 600 000 000')
        ->and($user->isSuperadmin())->toBeFalse()
        ->and(Hash::check('password', $user->password))->toBeFalse();

    Notification::assertSentTo($user, SetPasswordInvitation::class, function (SetPasswordInvitation $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        return str_contains($mail->actionUrl, '/reset-password/') && str_contains($mail->actionUrl, 'carmen%40example.com');
    });

    expect(AuditLog::where('action', 'user.created_by_admin')->where('actor_user_id', $admin->id)->where('on_behalf_of_user_id', $user->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'user.password_link_sent_by_admin')->where('subject_id', $user->id)->exists())->toBeTrue();
});

test('without an email the account gets an alias of the support mailbox and no invitation', function () {
    Notification::fake();
    config()->set('avytra.support.email', 'soporte@avytra.test');
    actingAsSuperadmin();

    Livewire::test('pages::admin.users.index')
        ->call('openCreate')
        ->set('form.name', 'José Pérez')
        ->set('form.email', '')
        ->set('form.send_password_link', true)
        ->call('create')
        ->assertHasNoErrors();

    $user = User::where('name', 'José Pérez')->sole();

    expect($user->email)->toBe('soporte+jose-perez@avytra.test')
        ->and($user->is_assisted)->toBeTrue()
        ->and(AuditLog::where('action', 'user.created_by_admin')->sole()->changes['after']['email_is_alias'])->toBeTrue();

    Notification::assertNothingSent();

    expect(CreateAssistedUser::aliasFor('José Pérez'))->toBe('soporte+jose-perez-2@avytra.test');
});

test('the email is required when the support mailbox is not configured', function () {
    config()->set('avytra.support.email', null);
    actingAsSuperadmin();

    Livewire::test('pages::admin.users.index')
        ->call('openCreate')
        ->set('form.name', 'Sin Email')
        ->set('form.email', '')
        ->call('create')
        ->assertHasErrors(['form.email' => 'required']);

    expect(User::where('name', 'Sin Email')->exists())->toBeFalse();
});

test('the detail page shows the businesses and listings of the account and the shortcuts to create more', function () {
    $user = User::factory()->assisted()->create(['name' => 'Ana Costa']);
    $business = Business::factory()->ownedBy($user)->create(['name' => 'Peluquería Ana']);
    $listing = Listing::factory()->forBusiness($business)->published()->create(['title' => 'Traspaso peluquería']);
    $foreign = Listing::factory()->published()->create(['title' => 'Publicación ajena']);

    actingAsSuperadmin();

    $this->get(route('admin.users.show', $user))
        ->assertOk()
        ->assertSee('Ana Costa')
        ->assertSee(__('Assisted account'))
        ->assertSee('Peluquería Ana')
        ->assertSee('Traspaso peluquería')
        ->assertDontSee('Publicación ajena')
        ->assertSee(route('admin.listings.show', $listing))
        ->assertSee(route('admin.businesses.create', ['propietario' => $user->id]))
        ->assertSee(route('admin.listings.create', ['propietario' => $user->id]));

    $this->get(route('admin.businesses.create', ['propietario' => $user->id]))->assertOk()->assertSee('Ana Costa');
});

test('the superadmin edits name, email and phone and only the changed fields are audited', function () {
    $admin = actingAsSuperadmin();
    $user = User::factory()->assisted()->create(['name' => 'Luis Mora', 'email' => 'soporte+luis@avytra.test', 'phone' => null]);
    $other = User::factory()->create();

    Livewire::test('pages::admin.users.show', ['user' => $user])
        ->set('form.email', $other->email)
        ->call('save')
        ->assertHasErrors(['form.email'])
        ->set('form.email', 'luis@example.com')
        ->set('form.phone', '611222333')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    $log = AuditLog::where('action', 'user.updated_by_admin')->sole();

    expect($user->email)->toBe('luis@example.com')
        ->and($user->phone)->toBe('611222333')
        ->and($user->name)->toBe('Luis Mora')
        ->and($log->actor_user_id)->toBe($admin->id)
        ->and($log->on_behalf_of_user_id)->toBe($user->id)
        ->and($log->changes['before'])->toBe(['email' => 'soporte+luis@avytra.test', 'phone' => null])
        ->and($log->changes['after'])->toBe(['email' => 'luis@example.com', 'phone' => '611222333']);

    Livewire::test('pages::admin.users.show', ['user' => $user])->call('save')->assertHasNoErrors();

    expect(AuditLog::where('action', 'user.updated_by_admin')->count())->toBe(1);
});

test('the superadmin resends the set-password invitation from the detail page', function () {
    Notification::fake();
    actingAsSuperadmin();
    $user = User::factory()->assisted()->create();

    Livewire::test('pages::admin.users.show', ['user' => $user])->call('sendPasswordLink');

    Notification::assertSentTo($user, SetPasswordInvitation::class);
    expect(AuditLog::where('action', 'user.password_link_sent_by_admin')->where('subject_id', $user->id)->exists())->toBeTrue();
});

test('the invitation link lets the person choose a password and sign in', function () {
    Notification::fake();
    actingAsSuperadmin();
    $user = User::factory()->assisted()->create(['email' => 'nueva@example.com']);

    Livewire::test('pages::admin.users.show', ['user' => $user])->call('sendPasswordLink');

    $token = null;
    Notification::assertSentTo($user, SetPasswordInvitation::class, function (SetPasswordInvitation $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    auth()->logout();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => 'nueva@example.com',
        'password' => 'ContraseñaNueva123!',
        'password_confirmation' => 'ContraseñaNueva123!',
    ])->assertSessionHasNoErrors();

    $this->post(route('login'), ['email' => 'nueva@example.com', 'password' => 'ContraseñaNueva123!'])->assertRedirect(route('dashboard'));
});
