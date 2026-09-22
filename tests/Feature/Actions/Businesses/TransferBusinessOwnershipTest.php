<?php

use App\Actions\Businesses\TransferBusinessOwnership;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;

test('the business changes hands and the change is audited with both owners', function () {
    $admin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();
    $previousOwner = $business->owner;
    $newOwner = User::factory()->create();

    app(TransferBusinessOwnership::class)->handle($business, $newOwner, $admin);

    expect($business->fresh()->owner_user_id)->toBe($newOwner->id)
        ->and($business->fresh()->updated_by_user_id)->toBe($admin->id);

    $log = AuditLog::sole();

    expect($log->action)->toBe('business.owner_changed')
        ->and($log->actor_user_id)->toBe($admin->id)
        ->and($log->on_behalf_of_user_id)->toBe($newOwner->id)
        ->and($log->changes)->toBe([
            'before' => ['owner_user_id' => $previousOwner->id],
            'after' => ['owner_user_id' => $newOwner->id],
        ]);
});

test('transferring to the current owner changes nothing and is not audited', function () {
    $admin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();

    app(TransferBusinessOwnership::class)->handle($business, $business->owner, $admin);

    expect(AuditLog::count())->toBe(0);
});
