<?php

use App\Enums\ListingEventType;
use App\Enums\ReminderStage;
use App\Models\Listing;
use App\Notifications\ListingExpired;
use App\Notifications\ListingFreshnessReminder;
use App\Support\Listings\ConfirmationLink;
use Illuminate\Http\Request;

test('the first reminder asks whether the listing is still available and links to the signed confirmation page', function () {
    $listing = Listing::factory()->published()->create(['title' => 'Traspaso cafetería']);

    $mail = (new ListingFreshnessReminder($listing, ReminderStage::First))->toMail($listing->owner());

    expect($mail->subject)->toContain('Traspaso cafetería')
        ->and($mail->actionUrl)->toStartWith(route('panel.listings.confirm', $listing))
        ->and($mail->actionUrl)->toContain('signature=')
        ->and(implode(' ', $mail->introLines))->toContain($listing->next_confirmation_at->translatedFormat('j \d\e F'));
});

test('the second reminder gives the exact pause date and the days left', function () {
    $listing = Listing::factory()->published(now()->subDays(config('avytra.freshness.second_reminder_days')))->create(['title' => 'Venta de gimnasio']);
    $daysLeft = config('avytra.freshness.confirmation_period_days') - config('avytra.freshness.second_reminder_days');

    $mail = (new ListingFreshnessReminder($listing, ReminderStage::Second))->toMail($listing->owner());

    expect($mail->subject)->toBe(__('“:title” will be paused in :days days', ['title' => 'Venta de gimnasio', 'days' => $daysLeft]))
        ->and(implode(' ', $mail->introLines))->toContain($listing->next_confirmation_at->translatedFormat('j \d\e F'));
});

test('the pause email explains that nothing was deleted and links to the reactivation page', function () {
    $listing = Listing::factory()->expired()->create(['title' => 'Tienda de barrio']);

    $mail = (new ListingExpired($listing))->toMail($listing->owner());

    expect($mail->subject)->toContain('Tienda de barrio')
        ->and($mail->actionText)->toBe(__('Reactivate the listing'))
        ->and($mail->actionUrl)->toStartWith(route('panel.listings.confirm', $listing))
        ->and($mail->actionUrl)->toContain('signature=');
});

test('emails are rendered with the brand theme', function () {
    $listing = Listing::factory()->published()->create();

    $html = (string) (new ListingFreshnessReminder($listing, ReminderStage::First))->toMail($listing->owner())->render();

    expect($html)->toContain('#B8F34A')->toContain('#101828');
});

test('a reminder that cannot be delivered is recorded in the listing history instead of being retried', function () {
    $listing = Listing::factory()->published()->create();

    (new ListingFreshnessReminder($listing, ReminderStage::Second))->failed(new RuntimeException('SMTP connection refused'));
    (new ListingExpired($listing))->failed(new RuntimeException('Mailbox unavailable'));

    $events = $listing->events()->where('type', ListingEventType::ReminderFailed)->get();

    expect($events)->toHaveCount(2)
        ->and($events->pluck('payload.stage')->all())->toEqualCanonicalizing(['second', 'expired'])
        ->and($listing->fresh()->load('events')->latestFailedReminder())->not->toBeNull();
});

test('the confirmation link is valid for the configured days and for that listing only', function () {
    $listing = Listing::factory()->published()->create();
    $other = Listing::factory()->published()->create();
    $url = ConfirmationLink::for($listing);

    expect(ConfirmationLink::isValid($listing, Request::create($url)))->toBeTrue()
        ->and(ConfirmationLink::isValid($other, Request::create($url)))->toBeFalse()
        ->and(ConfirmationLink::isValid($listing, Request::create(route('panel.listings.confirm', $listing))))->toBeFalse()
        ->and(ConfirmationLink::isValid($listing, Request::create($url.'x')))->toBeFalse();

    $this->travel(config('avytra.freshness.confirmation_link_ttl_days') + 1)->days();

    expect(ConfirmationLink::isValid($listing, Request::create($url)))->toBeFalse();
});
