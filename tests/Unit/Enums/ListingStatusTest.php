<?php

use App\Enums\ListingStatus;

/**
 * The complete transition table of docs/06-listing-lifecycle.md.
 *
 * @return array<string, array{ListingStatus, ListingStatus, bool}>
 */
function transitionTable(): array
{
    $allowed = [
        'draft' => ['published', 'archived'],
        'published' => ['paused', 'expired', 'sold', 'archived', 'suspended'],
        'paused' => ['published', 'sold', 'archived', 'suspended'],
        'expired' => ['published', 'sold', 'archived', 'suspended'],
        'sold' => ['archived'],
        'suspended' => ['published', 'archived'],
        'archived' => [],
    ];

    $cases = [];

    foreach (ListingStatus::cases() as $from) {
        foreach (ListingStatus::cases() as $to) {
            $cases["{$from->value} → {$to->value}"] = [$from, $to, in_array($to->value, $allowed[$from->value], true)];
        }
    }

    return $cases;
}

test('the transition table matches the lifecycle document', function (ListingStatus $from, ListingStatus $to, bool $expected) {
    expect($from->canTransitionTo($to))->toBe($expected);
})->with(transitionTable());

test('sold and archived are the only terminal states', function () {
    $terminal = array_map(fn (ListingStatus $status) => $status->value, array_filter(ListingStatus::cases(), fn (ListingStatus $status) => $status->isTerminal()));
    $open = array_map(fn (ListingStatus $status) => $status->value, ListingStatus::nonTerminal());

    expect(array_values($terminal))->toBe(['sold', 'archived'])
        ->and($open)->toBe(['draft', 'published', 'paused', 'expired', 'suspended']);
});

test('only published and sold listings may be shown publicly', function () {
    $visible = array_values(array_map(fn (ListingStatus $status) => $status->value, array_filter(ListingStatus::cases(), fn (ListingStatus $status) => $status->isPubliclyVisible())));

    expect($visible)->toBe(['published', 'sold']);
});

test('the owner cannot edit archived or suspended listings', function () {
    expect(ListingStatus::Archived->isEditableByOwner())->toBeFalse()
        ->and(ListingStatus::Suspended->isEditableByOwner())->toBeFalse()
        ->and(ListingStatus::Draft->isEditableByOwner())->toBeTrue()
        ->and(ListingStatus::Published->isEditableByOwner())->toBeTrue()
        ->and(ListingStatus::Expired->isEditableByOwner())->toBeTrue();
});
