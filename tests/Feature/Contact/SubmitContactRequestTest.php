<?php

use App\Actions\Contact\SubmitContactRequest;
use App\Models\Business;
use App\Models\ContactRequest;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ContactRequestReceived;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

test('a message is stored and emailed to the listing contact email with the sender as reply-to', function () {
    Notification::fake();
    $listing = Listing::factory()->published()->create(['contact_email' => 'buzon-publicacion@example.com', 'contact_name' => 'Marta']);

    $request = app(SubmitContactRequest::class)->handle($listing, null, '10.0.0.1', [
        'name' => 'Pedro Interesado',
        'email' => 'pedro@example.com',
        'phone' => '+34600999888',
        'message' => 'Me interesa el traspaso. ¿Podemos hablar esta semana?',
    ]);

    expect($request->listing_id)->toBe($listing->id)
        ->and($request->sender_user_id)->toBeNull()
        ->and($request->sender_name)->toBe('Pedro Interesado')
        ->and($request->sender_email)->toBe('pedro@example.com')
        ->and($request->sender_phone)->toBe('+34600999888')
        ->and($request->read_at)->toBeNull()
        ->and($request->ip_hash)->not->toBeNull()->not->toContain('10.0.0.1');

    Notification::assertSentOnDemand(
        ContactRequestReceived::class,
        fn (ContactRequestReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'buzon-publicacion@example.com'
            && $notification->request->is($request),
    );

    $mail = (new ContactRequestReceived($request))->toMail(new AnonymousNotifiable);

    expect($mail->replyTo)->toBe([['pedro@example.com', 'Pedro Interesado']])
        ->and($mail->subject)->toContain($listing->title)
        ->and($mail->actionUrl)->toBe(route('panel.messages.index'));

    $html = (string) $mail->render();

    expect($html)->toContain('Pedro Interesado')
        ->toContain('+34600999888')
        ->toContain('Me interesa el traspaso.')
        ->not->toContain($listing->owner()->email);
});

test('without a contact email the message goes to the account email of the owner', function () {
    Notification::fake();
    $owner = User::factory()->create(['email' => 'cuenta@example.com']);
    $listing = Listing::factory()->forBusiness(Business::factory()->ownedBy($owner)->create())->published()->create(['contact_email' => null]);

    app(SubmitContactRequest::class)->handle($listing, null, '10.0.0.1', [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'phone' => null,
        'message' => 'Hola, quiero más información.',
    ]);

    Notification::assertSentOnDemand(
        ContactRequestReceived::class,
        fn (ContactRequestReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'cuenta@example.com',
    );
});

test('a logged-in sender is linked by account', function () {
    Notification::fake();
    $user = User::factory()->create();
    $listing = Listing::factory()->published()->create();

    $request = app(SubmitContactRequest::class)->handle($listing, $user, '10.0.0.1', [
        'name' => $user->name,
        'email' => $user->email,
        'phone' => null,
        'message' => 'Hola, quiero más información.',
    ]);

    expect($request->sender_user_id)->toBe($user->id);
});

test('a delivery failure is recorded on the message so it still shows in the panel', function () {
    $request = ContactRequest::factory()->create();

    (new ContactRequestReceived($request))->failed(new RuntimeException('SMTP down'));

    $request->refresh();

    expect($request->delivery_failed_at)->not->toBeNull()
        ->and($request->delivery_error)->toBe('SMTP down');
});

test('messages sent today by the same visitor to the same listing are counted', function () {
    $listing = Listing::factory()->published()->create();
    $hash = SubmitContactRequest::hashIp('10.0.0.1');

    ContactRequest::factory()->forListing($listing)->count(2)->create(['ip_hash' => $hash]);
    ContactRequest::factory()->forListing($listing)->create(['ip_hash' => $hash, 'created_at' => now()->subDays(2)]);
    ContactRequest::factory()->forListing($listing)->create(['ip_hash' => SubmitContactRequest::hashIp('10.0.0.2')]);

    expect(SubmitContactRequest::sentToday($listing, '10.0.0.1'))->toBe(2)
        ->and(SubmitContactRequest::sentToday($listing, null))->toBe(0);
});
