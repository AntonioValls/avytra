<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\SetPasswordInvitation;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Password;

/**
 * Emails a person whose account the superadmin created so they can choose their own password.
 * Reuses Fortify's password broker: the link is the ordinary reset form, valid for the
 * configured expiry. Always audited.
 */
class SendSetPasswordLink
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $user, User $actor): void
    {
        $token = Password::broker()->createToken($user);

        $user->notify(new SetPasswordInvitation($token));

        $this->audit->log(action: 'user.password_link_sent_by_admin', subject: $user, actor: $actor, onBehalfOf: $user);
    }
}
