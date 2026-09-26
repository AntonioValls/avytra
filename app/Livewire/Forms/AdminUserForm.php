<?php

namespace App\Livewire\Forms;

use App\Actions\Users\CreateAssistedUser;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Account data handled by the superadmin (docs/03, "Asistencia"): create on behalf of a
 * person or edit name, email and phone later. The email is optional only when creating and
 * only if the support mailbox can provide an alias.
 */
class AdminUserForm extends Form
{
    public ?int $userId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public bool $send_password_link = true;

    public function fillFromUser(User $user): void
    {
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->send_password_link = false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $emailRequired = $this->userId !== null || ! CreateAssistedUser::canBuildAlias();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                $emailRequired ? 'required' : 'nullable',
                'string',
                app()->isProduction() ? 'email:rfc,dns' : 'email',
                'max:255',
                $this->userId === null ? Rule::unique(User::class) : Rule::unique(User::class)->ignore($this->userId),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'send_password_link' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('name'),
            'email' => __('email'),
            'phone' => __('phone'),
        ];
    }

    /**
     * @return array{name: string, email: string, phone: string}
     */
    public function toAttributes(): array
    {
        return [
            'name' => trim($this->name),
            'email' => trim($this->email),
            'phone' => trim($this->phone),
        ];
    }
}
