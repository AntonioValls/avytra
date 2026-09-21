<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('avytra:superadmin {email : Email of an existing user} {--revoke : Demote the user back to a regular user}')]
#[Description('Grant (or revoke) the superadmin role. This is the only way to change roles.')]
class MakeSuperadmin extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email [{$email}].");

            return self::FAILURE;
        }

        $role = $this->option('revoke') ? UserRole::User : UserRole::Superadmin;

        if ($user->role === $role) {
            $this->info("{$user->email} already has the role [{$role->value}].");

            return self::SUCCESS;
        }

        $user->role = $role;
        $user->save();

        $this->info("{$user->email} is now [{$role->value}].");

        return self::SUCCESS;
    }
}
