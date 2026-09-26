<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\User;
use App\Support\Audit\AuditActions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The audit log (docs/16): every administrative action on somebody else's resources,
 * filterable by actor, action, resource type and affected user. Read only.
 */
new class extends Component {
    use WithPagination;

    #[Url(as: 'actor', except: '')]
    public string $actor = '';

    #[Url(as: 'accion', except: '')]
    public string $action = '';

    #[Url(as: 'recurso', except: '')]
    public string $subjectType = '';

    #[Url(as: 'usuario', except: '')]
    public string $user = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function rendering(View $view): void
    {
        $view->title(__('Audit log'));
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['actor', 'action', 'subjectType', 'user'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        $subjectType = $this->subjectTypes()[$this->subjectType] ?? null;

        return AuditLog::query()
            ->with(['actor', 'onBehalfOf', 'subject'])
            ->when($this->actor !== '', fn (Builder $query) => $query->where('actor_user_id', (int) $this->actor))
            ->when($this->action !== '', fn (Builder $query) => $query->where('action', $this->action))
            ->when($subjectType !== null, fn (Builder $query) => $query->where('subject_type', $subjectType))
            ->when($this->user !== '', function (Builder $query): void {
                $userId = (int) $this->user;
                $query->where(function (Builder $query) use ($userId): void {
                    $query->where('on_behalf_of_user_id', $userId)
                        ->orWhere('actor_user_id', $userId)
                        ->orWhere(fn (Builder $query) => $query->where('subject_type', (new User)->getMorphClass())->where('subject_id', $userId));
                });
            })
            ->latest('id')
            ->paginate(config('avytra.pagination.admin_rows'));
    }

    /**
     * Superadmins (and anybody who ever acted) for the actor filter.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function actors(): Collection
    {
        return User::query()->whereIn('id', AuditLog::query()->select('actor_user_id')->whereNotNull('actor_user_id'))->orderBy('name')->get();
    }

    #[Computed]
    public function filteredUser(): ?User
    {
        return $this->user === '' ? null : User::query()->find((int) $this->user);
    }

    /**
     * @return array<string, string>
     */
    public function subjectTypes(): array
    {
        return [
            'user' => (new User)->getMorphClass(),
            'business' => (new Business)->getMorphClass(),
            'listing' => (new Listing)->getMorphClass(),
            'report' => (new ListingReport)->getMorphClass(),
        ];
    }

    public function subjectLabel(AuditLog $entry): string
    {
        $subject = $entry->subject;

        $name = match (true) {
            $subject instanceof User => $subject->name,
            $subject instanceof Business => $subject->name,
            $subject instanceof Listing => $subject->title ?? __('Untitled draft'),
            $subject instanceof ListingReport => __('Report #:id', ['id' => $subject->id]),
            $subject instanceof Model => '#'.$subject->getKey(),
            default => $entry->subject_id === null ? '—' : __('Deleted (#:id)', ['id' => $entry->subject_id]),
        };

        return $name;
    }

    public function subjectUrl(AuditLog $entry): ?string
    {
        $subject = $entry->subject;

        return match (true) {
            $subject instanceof User => route('admin.users.show', $subject),
            $subject instanceof Business => route('admin.businesses.edit', $subject),
            $subject instanceof Listing => route('admin.listings.show', $subject),
            $subject instanceof ListingReport => route('admin.reports.index', ['estado' => $subject->status->value]),
            default => null,
        };
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl" level="1">{{ __('Audit log') }}</flux:heading>
        <flux:text>{{ __('Every administrative action on other people\'s resources, with who did it and on whose behalf.') }}</flux:text>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:select variant="listbox" wire:model.live="actor" :placeholder="__('Any actor')">
            <flux:select.option value="">{{ __('Any actor') }}</flux:select.option>
            @foreach ($this->actors as $person)
                <flux:select.option :value="$person->id">{{ $person->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select variant="listbox" searchable wire:model.live="action" :placeholder="__('Any action')">
            <flux:select.option value="">{{ __('Any action') }}</flux:select.option>
            @foreach (App\Support\Audit\AuditActions::all() as $key)
                <flux:select.option :value="$key">{{ AuditActions::label($key) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select variant="listbox" wire:model.live="subjectType" :placeholder="__('Any resource')">
            <flux:select.option value="">{{ __('Any resource') }}</flux:select.option>
            <flux:select.option value="user">{{ __('Users') }}</flux:select.option>
            <flux:select.option value="business">{{ __('Businesses') }}</flux:select.option>
            <flux:select.option value="listing">{{ __('Listings') }}</flux:select.option>
            <flux:select.option value="report">{{ __('Reports') }}</flux:select.option>
        </flux:select>
    </div>

    @if ($this->filteredUser)
        <flux:callout icon="user" variant="secondary">
            <flux:callout.text>{{ __('Showing only the entries that involve :name.', ['name' => $this->filteredUser->name]) }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" variant="ghost" wire:click="$set('user', '')">{{ __('Show everything') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    @if ($this->entries->isEmpty())
        <x-empty-state icon="clipboard-document-list" :heading="__('No entries match.')" :text="__('Administrative actions will appear here as they happen.')" />
    @else
        <flux:table :paginate="$this->entries">
            <flux:table.columns>
                <flux:table.column>{{ __('When') }}</flux:table.column>
                <flux:table.column>{{ __('Action') }}</flux:table.column>
                <flux:table.column>{{ __('Resource') }}</flux:table.column>
                <flux:table.column>{{ __('Actor') }}</flux:table.column>
                <flux:table.column>{{ __('On behalf of') }}</flux:table.column>
                <flux:table.column>{{ __('Changes') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->entries as $entry)
                    <flux:table.row :key="$entry->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $entry->created_at?->translatedFormat('j M Y, H:i') }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ AuditActions::label($entry->action) }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($url = $this->subjectUrl($entry))
                                <flux:link :href="$url" wire:navigate>{{ $this->subjectLabel($entry) }}</flux:link>
                            @else
                                {{ $this->subjectLabel($entry) }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $entry->actor?->name ?? __('System') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($entry->onBehalfOf)
                                <flux:link :href="route('admin.users.show', $entry->onBehalfOf)" wire:navigate>{{ $entry->onBehalfOf->name }}</flux:link>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($entry->changes)
                                <details class="text-xs">
                                    <summary class="cursor-pointer text-transfer">{{ __('See changes') }}</summary>
                                    <pre class="mt-2 max-w-xs overflow-x-auto whitespace-pre-wrap rounded-md bg-mist p-2 font-mono text-[11px] text-ink dark:bg-zinc-800 dark:text-white">{{ json_encode($entry->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
