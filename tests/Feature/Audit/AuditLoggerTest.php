<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditLogger;

test('it records the actor, the affected user, the subject and the changes', function () {
    $admin = User::factory()->superadmin()->create();
    $owner = User::factory()->create();

    $log = app(AuditLogger::class)->log(
        action: 'user.created_by_admin',
        subject: $owner,
        actor: $admin,
        onBehalfOf: $owner,
        changes: ['after' => ['name' => $owner->name]],
    );

    expect($log)->toBeInstanceOf(AuditLog::class)
        ->and($log->actor->is($admin))->toBeTrue()
        ->and($log->onBehalfOf->is($owner))->toBeTrue()
        ->and($log->subject->is($owner))->toBeTrue()
        ->and($log->changes)->toBe(['after' => ['name' => $owner->name]])
        ->and($log->created_at)->not->toBeNull();
});

test('it can record system actions without an actor or subject', function () {
    $log = app(AuditLogger::class)->log(action: 'listings.freshness_processed');

    expect($log->actor_user_id)->toBeNull()
        ->and($log->subject_type)->toBeNull()
        ->and($log->subject_id)->toBeNull();
});

test('deleting the actor keeps the audit entry', function () {
    $admin = User::factory()->superadmin()->create();
    $log = AuditLog::factory()->create(['actor_user_id' => $admin->id]);

    $admin->delete();

    expect($log->fresh())->not->toBeNull()
        ->and($log->fresh()->actor_user_id)->toBeNull();
});
