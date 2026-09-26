<?php

use App\Actions\Users\CreateAssistedUser;
use App\Enums\UserRole;
use App\Livewire\Forms\AdminUserForm;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every account on the platform with search and the "assisted accounts" filter, plus the
 * "new user on behalf of somebody" modal (docs/03, docs/09).
 */
new class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'condicion', except: '')]
    public string $condition = '';

    public AdminUserForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function rendering(View $view): void
    {
        $view->title(__('Users'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCondition(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return User::query()
            ->withCount('businesses')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($this->condition === 'assisted', fn (Builder $query) => $query->where('is_assisted', true))
            ->when($this->condition === 'superadmin', fn (Builder $query) => $query->where('role', UserRole::Superadmin))
            ->latest('id')
            ->paginate(config('avytra.pagination.admin_rows'));
    }

    #[Computed]
    public function aliasAvailable(): bool
    {
        return CreateAssistedUser::canBuildAlias();
    }

    public function openCreate(): void
    {
        $this->authorize('create', User::class);

        $this->form->reset();
        $this->resetErrorBag();

        Flux::modal('create-user')->show();
    }

    public function create(CreateAssistedUser $action): void
    {
        $this->authorize('create', User::class);

        $this->form->validate();

        /** @var User $actor */
        $actor = Auth::user();
        $attributes = $this->form->toAttributes();

        $user = $action->handle($actor, $attributes, $this->form->send_password_link && $attributes['email'] !== '');

        Flux::modal('create-user')->close();
        Flux::toast(variant: 'success', text: __('Account created for :name.', ['name' => $user->name]));

        $this->redirectRoute('admin.users.show', ['user' => $user], navigate: true);
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
            <flux:text>{{ __('Every account on the platform. Create one on behalf of a person who cannot register by themselves.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="user-plus" wire:click="openCreate">{{ __('New user') }}</flux:button>
    </div>

    <div class="grid gap-3 sm:grid-cols-[2fr_1fr]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name, email or phone')" clearable />

        <flux:select variant="listbox" wire:model.live="condition" :placeholder="__('Any account')">
            <flux:select.option value="">{{ __('Any account') }}</flux:select.option>
            <flux:select.option value="assisted">{{ __('Assisted accounts') }}</flux:select.option>
            <flux:select.option value="superadmin">{{ __('Superadmins') }}</flux:select.option>
        </flux:select>
    </div>

    @if ($this->users->isEmpty())
        <x-empty-state icon="users" :heading="__('No users match.')" :text="__('Try another search or create an account on behalf of a person.')" />
    @else
        <flux:table :paginate="$this->users">
            <flux:table.columns>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Phone') }}</flux:table.column>
                <flux:table.column>{{ __('Businesses') }}</flux:table.column>
                <flux:table.column>{{ __('Registered') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell variant="strong">
                            <div class="flex flex-col gap-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:link :href="route('admin.users.show', $user)" wire:navigate>{{ $user->name }}</flux:link>
                                    @if ($user->isSuperadmin())
                                        <flux:badge size="sm" color="zinc">{{ $user->role->label() }}</flux:badge>
                                    @endif
                                    @if ($user->is_assisted)
                                        <flux:badge size="sm" color="blue" icon="phone">{{ __('Assisted') }}</flux:badge>
                                    @endif
                                </div>
                                <span class="text-xs font-normal text-slate">{{ $user->email }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $user->phone ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $user->businesses_count }}</flux:table.cell>
                        <flux:table.cell>{{ $user->created_at?->translatedFormat('j M Y') }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="sm" variant="ghost" icon="arrow-right" :href="route('admin.users.show', $user)" wire:navigate>{{ __('Manage') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="create-user" class="w-full max-w-lg">
        <form wire:submit="create" class="flex flex-col gap-6">
            <div>
                <flux:heading size="lg">{{ __('New user on behalf of a person') }}</flux:heading>
                <flux:subheading>{{ __('The account is created verified with a random password. The person can set their own password from the email, or you manage everything for them.') }}</flux:subheading>
            </div>

            <flux:input wire:model="form.name" :label="__('Name')" maxlength="255" autofocus />
            <flux:input
                wire:model.live.debounce.300ms="form.email"
                type="email"
                :label="__('Email')"
                :description="$this->aliasAvailable ? __('Optional. Without an email, an alias of the support mailbox is used and you will receive the reminders.') : __('Required: configure AVYTRA_SUPPORT_EMAIL to allow accounts without an email.')"
                maxlength="255"
            />
            <flux:input wire:model="form.phone" type="tel" :label="__('Phone')" maxlength="30" />
            <flux:checkbox wire:model="form.send_password_link" :label="__('Send an email so they can set their password')" :disabled="trim($form->email) === ''" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Create account') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
