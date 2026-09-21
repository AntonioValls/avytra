<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Records administrative and sensitive actions so they remain traceable.
 *
 * Only actions listed in docs/16-security-and-privacy.md are logged: anything the
 * superadmin does on another user's resources, ownership changes, suspensions,
 * users created on behalf of somebody, report resolutions and manual slug changes.
 */
class AuditLogger
{
    public function __construct(private Request $request) {}

    /**
     * @param  array{before?: array<string, mixed>, after?: array<string, mixed>}|null  $changes
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?User $actor = null,
        ?User $onBehalfOf = null,
        ?array $changes = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_user_id' => $actor?->getKey(),
            'on_behalf_of_user_id' => $onBehalfOf?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes,
            'ip_address' => $this->request->ip(),
        ]);
    }
}
