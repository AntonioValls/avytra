<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Name, email and phone of an account, changed by the superadmin (for instance when an
 * assisted person finally gets an email address). Only the fields that change are audited.
 */
class UpdateUserByAdmin
{
    private const FIELDS = ['name', 'email', 'phone'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null}  $attributes
     */
    public function handle(User $user, User $actor, array $attributes): User
    {
        $phone = trim((string) ($attributes['phone'] ?? ''));

        $user->fill([
            'name' => trim($attributes['name']),
            'email' => trim($attributes['email']),
            'phone' => $phone === '' ? null : $phone,
        ]);

        $dirty = array_intersect_key($user->getDirty(), array_flip(self::FIELDS));

        if ($dirty === []) {
            return $user;
        }

        return DB::transaction(function () use ($user, $actor, $dirty): User {
            $before = array_intersect_key($user->getOriginal(), $dirty);

            $user->save();

            $this->audit->log(
                action: 'user.updated_by_admin',
                subject: $user,
                actor: $actor,
                onBehalfOf: $user,
                changes: ['before' => $before, 'after' => $dirty],
            );

            return $user;
        });
    }
}
