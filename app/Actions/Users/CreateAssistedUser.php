<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The "phone call" case (docs/03, "Asistencia por parte del superadmin"): the superadmin
 * creates an account for somebody so their business can be published without them doing
 * anything. Random password, email marked as verified by the admin, is_assisted = true.
 *
 * Without an email address, Fortify still needs a unique one: an alias of the support
 * mailbox (soporte+nombre@dominio) is used, so reminders land in the superadmin's inbox.
 */
class CreateAssistedUser
{
    public function __construct(private AuditLogger $audit, private SendSetPasswordLink $sendSetPasswordLink) {}

    /**
     * @param  array{name: string, email?: string|null, phone?: string|null}  $attributes
     */
    public function handle(User $actor, array $attributes, bool $sendPasswordLink = false): User
    {
        $email = trim((string) ($attributes['email'] ?? ''));
        $usesAlias = $email === '';

        if ($usesAlias) {
            $email = (string) self::aliasFor($attributes['name']);
        }

        return DB::transaction(function () use ($actor, $attributes, $email, $usesAlias, $sendPasswordLink): User {
            $user = new User([
                'name' => trim($attributes['name']),
                'email' => $email,
                'password' => Str::password(32),
                'phone' => self::nullableTrim($attributes['phone'] ?? null),
            ]);
            $user->is_assisted = true;
            $user->save();
            // Verified by the admin: the person never has to click a verification link.
            $user->markEmailAsVerified();

            $this->audit->log(
                action: 'user.created_by_admin',
                subject: $user,
                actor: $actor,
                onBehalfOf: $user,
                changes: ['after' => ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'email_is_alias' => $usesAlias]],
            );

            if ($sendPasswordLink && ! $usesAlias) {
                $this->sendSetPasswordLink->handle($user, $actor);
            }

            return $user;
        });
    }

    /**
     * Unique address delivered to the support mailbox for people without email, built as
     * "local+slug-N@domain" from avytra.support.email. Null when support has no email configured.
     */
    public static function aliasFor(string $name): ?string
    {
        $support = config('avytra.support.email');

        if (! is_string($support) || ! str_contains($support, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $support, 2);
        $base = Str::slug($name) ?: 'cuenta';
        $candidate = "{$local}+{$base}@{$domain}";
        $suffix = 1;

        while (User::query()->where('email', $candidate)->exists()) {
            $suffix++;
            $candidate = "{$local}+{$base}-{$suffix}@{$domain}";
        }

        return $candidate;
    }

    public static function canBuildAlias(): bool
    {
        return self::aliasFor('x') !== null;
    }

    private static function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
