<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $this->rejectBots($input);

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }

    /**
     * Honeypot and minimum submit time (docs/16, "Spam y bots"): the form carries a hidden
     * "website" field humans never fill and the moment it was opened.
     *
     * @param  array<string, string>  $input
     */
    private function rejectBots(array $input): void
    {
        $honeypotFilled = trim((string) ($input['website'] ?? '')) !== '';
        $openedAt = (int) ($input['form_opened_at'] ?? 0);
        $tooFast = $openedAt > 0 && now()->getTimestamp() - $openedAt < (int) config('avytra.registration.min_seconds_to_submit');

        if ($honeypotFilled || $tooFast) {
            throw ValidationException::withMessages(['email' => __('We could not create the account. Please try again.')]);
        }
    }
}
