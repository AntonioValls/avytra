<?php

use App\Actions\Businesses\CreateBusiness;
use App\Actions\Businesses\UpdateBusiness;
use App\Actions\Locations\SaveBusinessLocation;
use App\Enums\AcquisitionChannel;
use App\Enums\BusinessType;
use App\Enums\Disclosure;
use App\Enums\EmployeeRange;
use App\Enums\LegalForm;
use App\Enums\LocationVisibility;
use App\Enums\LogisticsType;
use App\Enums\OnlineBusinessType;
use App\Enums\TechnologyPlatform;
use App\Enums\WebsiteVisibility;
use App\Livewire\Forms\BusinessForm;
use App\Livewire\Forms\OnlineProfileForm;
use App\Livewire\LocationPickerComponent;
use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Create / edit a business. Shared by the panel (/panel/empresas) and the admin
 * (/admin/empresas), where the superadmin also picks the owner.
 * Orchestrates only: authorize → validate → Actions → feedback.
 */
new class extends LocationPickerComponent {
    #[Locked]
    public ?int $businessId = null;

    #[Locked]
    public bool $adminContext = false;

    public BusinessForm $form;

    public OnlineProfileForm $online;

    public ?int $ownerUserId = null;

    public string $ownerSearch = '';

    /**
     * $admin comes from the route default set in routes/admin.php.
     */
    public function mount(?Business $business = null, bool $admin = false): void
    {
        $this->adminContext = $admin;

        if ($business === null) {
            $this->authorize('create', Business::class);
            $this->ownerUserId = Auth::id();

            return;
        }

        $this->authorize('update', $business);

        $this->businessId = $business->id;
        $this->ownerUserId = $business->owner_user_id;
        $this->form->fillFromBusiness($business);

        if ($business->location) {
            $this->location->fillFromLocation($business->location);
        }

        if ($business->onlineProfile) {
            $this->online->fillFromOnlineProfile($business->onlineProfile);
        }
    }

    public function rendering(View $view): void
    {
        $view->title($this->businessId === null ? __('New business') : __('Edit business'));
    }

    public function updatedFormCategoryId(): void
    {
        $this->form->subcategory_id = null;
    }

    public function updatedLocationProvinceId(): void
    {
        $this->location->municipality_id = null;
    }

    protected function authorizeLocationChange(): void
    {
        if ($this->businessId === null) {
            $this->authorize('create', Business::class);

            return;
        }

        $this->authorize('update', Business::query()->findOrFail($this->businessId));
    }

    public function addSocialProfile(): void
    {
        $this->online->addSocialProfile();
    }

    public function removeSocialProfile(int $index): void
    {
        $this->online->removeSocialProfile($index);
    }

    public function save(CreateBusiness $createBusiness, UpdateBusiness $updateBusiness, SaveBusinessLocation $saveLocation): void
    {
        /** @var User $actor */
        $actor = Auth::user();
        $business = $this->businessId === null ? null : Business::query()->findOrFail($this->businessId);

        if ($business === null) {
            $this->authorize('create', Business::class);
        } else {
            $this->authorize('update', $business);
        }

        $type = $this->form->type();

        $this->validate(...$this->validationFor($type));

        $owner = $this->resolveOwner($business, $actor);
        $onlineProfile = $type->requiresOnlineProfile() ? $this->online->toAttributes() : null;

        DB::transaction(function () use ($business, $owner, $actor, $type, $onlineProfile, $createBusiness, $updateBusiness, $saveLocation): void {
            $business = $business === null
                ? $createBusiness->handle($owner, $actor, $this->form->toAttributes(), $onlineProfile)
                : $updateBusiness->handle($business, $actor, $this->form->toAttributes(), $onlineProfile);

            if ($type->requiresLocation()) {
                $saveLocation->handle($business, $this->location->toAttributes());
            }
        });

        Flux::toast(variant: 'success', text: $this->businessId === null ? __('Business created.') : __('Business updated.'));

        $this->redirectRoute($this->adminContext ? 'admin.businesses.index' : 'businesses.index', navigate: true);
    }

    /**
     * Rules, messages and attribute names for the forms that apply to the chosen type.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>, 2: array<string, string>}
     */
    private function validationFor(BusinessType $type): array
    {
        $rules = $this->prefixed('form', $this->form->rules());
        $attributes = $this->prefixed('form', $this->form->validationAttributes());

        if ($type->requiresLocation()) {
            $rules += $this->prefixed('location', $this->location->rules());
            $attributes += $this->prefixed('location', $this->location->validationAttributes());
        }

        if ($type->requiresOnlineProfile()) {
            $rules += $this->prefixed('online', $this->online->rules());
            $attributes += $this->prefixed('online', $this->online->validationAttributes());
        }

        if ($this->adminContext) {
            $rules['ownerUserId'] = ['required', 'integer', Rule::exists('users', 'id')];
            $attributes['ownerUserId'] = __('owner');
        }

        return [$rules, [], $attributes];
    }

    /**
     * @template TValue
     *
     * @param  array<string, TValue>  $items
     * @return array<string, TValue>
     */
    private function prefixed(string $prefix, array $items): array
    {
        $prefixed = [];

        foreach ($items as $key => $value) {
            $prefixed["{$prefix}.{$key}"] = $value;
        }

        return $prefixed;
    }

    private function resolveOwner(?Business $business, User $actor): User
    {
        if ($this->adminContext) {
            return User::query()->findOrFail($this->ownerUserId);
        }

        return $business?->owner ?? $actor;
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function sectors(): Collection
    {
        return Category::query()->roots()->active()->get();
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function subsectors(): Collection
    {
        if ($this->form->category_id === null) {
            return new Collection;
        }

        return Category::query()->active()->where('parent_id', $this->form->category_id)->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Province>
     */
    #[Computed]
    public function provinces(): Collection
    {
        return Province::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Municipality>
     */
    #[Computed]
    public function municipalities(): Collection
    {
        if ($this->location->province_id === null) {
            return new Collection;
        }

        return Municipality::query()->where('province_id', $this->location->province_id)->orderBy('name')->get();
    }

    /**
     * Owner candidates for the superadmin: a short server-side search plus the current owner.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function ownerOptions(): Collection
    {
        $search = trim($this->ownerSearch);

        $users = User::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(25)
            ->get();

        if ($this->ownerUserId !== null && ! $users->contains('id', $this->ownerUserId)) {
            $current = User::query()->find($this->ownerUserId);

            if ($current !== null) {
                $users->prepend($current);
            }
        }

        return $users;
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl" level="1">{{ $businessId === null ? __('New business') : __('Edit business') }}</flux:heading>
        <flux:text>{{ __('The business is the company itself. Publishing it for sale or transfer is a separate step.') }}</flux:text>
    </div>

    <form wire:submit="save" class="flex max-w-3xl flex-col gap-10">
        @if ($adminContext)
            <section class="flex flex-col gap-4">
                <flux:heading size="lg" level="2">{{ __('Owner') }}</flux:heading>
                <flux:select
                    wire:model="ownerUserId"
                    variant="listbox"
                    searchable
                    :filter="false"
                    :label="__('Owner')"
                    :description="__('The user who will own this business. You stay recorded as its creator.')"
                    :placeholder="__('Choose a user')"
                >
                    <x-slot name="search">
                        <flux:select.search wire:model.live.debounce.300ms="ownerSearch" :placeholder="__('Search by name or email')" />
                    </x-slot>

                    @foreach ($this->ownerOptions as $user)
                        <flux:select.option :value="$user->id" wire:key="owner-{{ $user->id }}">{{ $user->name }} · {{ $user->email }}</flux:select.option>
                    @endforeach
                </flux:select>
            </section>
        @endif

        <section class="flex flex-col gap-4">
            <flux:heading size="lg" level="2">{{ __('Business type') }}</flux:heading>

            <flux:radio.group wire:model.live="form.business_type" variant="cards" class="max-sm:flex-col">
                @foreach (BusinessType::cases() as $type)
                    <flux:radio :value="$type->value" :label="$type->label()" :description="$type->description()" :icon="$type->icon()" />
                @endforeach
            </flux:radio.group>
        </section>

        <section class="flex flex-col gap-6">
            <flux:heading size="lg" level="2">{{ __('Basic information') }}</flux:heading>

            <flux:input
                wire:model="form.name"
                :label="__('Trade name')"
                :description="__('It can be generic (for example, “Bakery in the centre of Castellón”) if you prefer not to reveal the brand.')"
                maxlength="120"
                required
            />

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:select wire:model.live="form.category_id" variant="listbox" searchable :label="__('Sector')" :placeholder="__('Choose a sector')">
                    @foreach ($this->sectors as $sector)
                        <flux:select.option :value="$sector->id" wire:key="sector-{{ $sector->id }}">{{ $sector->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select
                    wire:model="form.subcategory_id"
                    variant="listbox"
                    searchable
                    clearable
                    :label="__('Subsector')"
                    :placeholder="$this->subsectors->isEmpty() ? __('Choose a sector first') : __('Optional')"
                    :disabled="$this->subsectors->isEmpty()"
                >
                    @foreach ($this->subsectors as $subsector)
                        <flux:select.option :value="$subsector->id" wire:key="subsector-{{ $subsector->id }}">{{ $subsector->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:input
                wire:model="form.tagline"
                :label="__('Short description')"
                :description="__('One sentence, up to 160 characters. Used on cards and search results.')"
                maxlength="160"
            />

            <flux:textarea
                wire:model="form.description"
                :label="__('Description')"
                :description="__('Plain text with paragraphs. At least 200 characters will be required to publish.')"
                rows="8"
            />

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="form.founded_year" type="number" :label="__('Founding year')" min="1800" :max="now()->year" />

                <flux:select variant="listbox" wire:model="form.employee_range" :label="__('Employees')" :placeholder="__('Choose a range')">
                    @foreach (EmployeeRange::cases() as $range)
                        <flux:select.option :value="$range->value">{{ $range->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </section>

        <section class="flex flex-col gap-6">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg" level="2">{{ __('Legal and web details') }}</flux:heading>
                <flux:text>{{ __('Private unless you decide otherwise. The legal name is never published.') }}</flux:text>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="form.legal_name" :label="__('Legal name')" :badge="__('Private')" maxlength="160" />

                <flux:select variant="listbox" wire:model="form.legal_form" :label="__('Legal form')" :placeholder="__('Choose a legal form')">
                    @foreach (LegalForm::cases() as $legalForm)
                        <flux:select.option :value="$legalForm->value">{{ $legalForm->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:switch wire:model="form.show_legal_form" :label="__('Show the legal form publicly')" />

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="form.website_url" type="url" :label="__('Website')" placeholder="https://" maxlength="255" />

                <flux:select variant="listbox" wire:model="form.website_visibility" :label="__('Website visibility')" :description="__('Private: the website is only given to interested buyers.')">
                    @foreach (WebsiteVisibility::cases() as $visibility)
                        <flux:select.option :value="$visibility->value">{{ $visibility->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </section>

        @if ($this->form->type()->requiresLocation())
            <section class="flex flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg" level="2">{{ __('Location') }}</flux:heading>
                    <flux:text>{{ __('Where the premises are. You decide how much is shown publicly.') }}</flux:text>
                </div>

                @include('partials.location-fields')
            </section>
        @endif

        @if ($this->form->type()->requiresOnlineProfile())
            <section class="flex flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <flux:heading size="lg" level="2">{{ __('Online business') }}</flux:heading>
                    <flux:text>{{ __('What buyers of online businesses look at first.') }}</flux:text>
                </div>

                <flux:radio.group wire:model="online.online_business_type" variant="cards" :label="__('Online business type')" class="grid grid-cols-1 sm:grid-cols-2">
                    @foreach (OnlineBusinessType::cases() as $onlineType)
                        <flux:radio :value="$onlineType->value" :label="$onlineType->label()" />
                    @endforeach
                </flux:radio.group>

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:select variant="listbox" wire:model.live="online.technology_platform" :label="__('Technology platform')" :placeholder="__('Choose a platform')">
                        @foreach (TechnologyPlatform::cases() as $platform)
                            <flux:select.option :value="$platform->value">{{ $platform->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @if ($online->technology_platform === TechnologyPlatform::Other->value)
                        <flux:input wire:model="online.technology_platform_other" :label="__('Which platform?')" maxlength="80" />
                    @endif
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:input wire:model="online.monthly_visits" type="number" min="0" :label="__('Monthly visits')" />

                    <flux:select variant="listbox" wire:model="online.monthly_visits_disclosure" :label="__('Visits disclosure')">
                        @foreach (Disclosure::cases() as $disclosure)
                            <flux:select.option :value="$disclosure->value">{{ $disclosure->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:accordion transition>
                    <flux:accordion.item :heading="__('Add more details about the online business')">
                        <div class="flex flex-col gap-6 pt-4">
                            <div class="grid gap-6 sm:grid-cols-2">
                                <flux:input wire:model="online.domain_registered_year" type="number" min="1985" :max="now()->year" :label="__('Domain registration year')" />
                                <flux:input wire:model="online.recurring_revenue_percent" type="number" min="0" max="100" :label="__('Recurring revenue percentage')" />
                                <flux:input wire:model="online.registered_users" type="number" min="0" :label="__('Registered users')" />
                                <flux:input wire:model="online.active_customers" type="number" min="0" :label="__('Active customers')" />
                                <flux:input wire:model="online.monthly_orders" type="number" min="0" :label="__('Monthly orders')" />
                                <flux:select variant="listbox" wire:model="online.logistics_type" :label="__('Logistics')" :placeholder="__('Choose an option')">
                                    @foreach (LogisticsType::cases() as $logistics)
                                        <flux:select.option :value="$logistics->value">{{ $logistics->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>

                            <flux:checkbox.group wire:model="online.acquisition_channels" :label="__('Acquisition channels')" class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach (AcquisitionChannel::cases() as $channel)
                                    <flux:checkbox :value="$channel->value" :label="$channel->label()" />
                                @endforeach
                            </flux:checkbox.group>

                            <flux:input
                                wire:model="online.sells_on_marketplaces"
                                :label="__('Marketplaces where it sells')"
                                :description="__('Separate with commas: Amazon, Etsy, eBay…')"
                                maxlength="255"
                            />

                            <div class="flex flex-col gap-3">
                                <flux:label>{{ __('Social profiles') }}</flux:label>
                                <flux:description>{{ __('They share the website visibility.') }}</flux:description>

                                @foreach ($online->social_profiles as $index => $profile)
                                    <div class="grid gap-3 sm:grid-cols-[10rem_1fr_auto]" wire:key="social-{{ $index }}">
                                        <flux:input wire:model="online.social_profiles.{{ $index }}.network" :placeholder="__('Network')" maxlength="40" />
                                        <flux:input wire:model="online.social_profiles.{{ $index }}.url" type="url" placeholder="https://" maxlength="255" />
                                        <flux:button type="button" variant="ghost" icon="trash" wire:click="removeSocialProfile({{ $index }})" :aria-label="__('Remove')" />
                                    </div>
                                @endforeach

                                <div>
                                    <flux:button type="button" size="sm" icon="plus" wire:click="addSocialProfile">{{ __('Add social profile') }}</flux:button>
                                </div>
                            </div>

                            <div class="flex flex-col gap-3">
                                <flux:switch wire:model="online.has_stock" :label="__('Includes stock')" />
                                <flux:switch wire:model="online.team_included" :label="__('The team stays with the business')" />
                            </div>
                        </div>
                    </flux:accordion.item>
                </flux:accordion>
            </section>
        @endif

        <div class="flex flex-col-reverse gap-3 border-t border-zinc-200 pt-6 sm:flex-row sm:justify-end">
            <flux:button :href="route($adminContext ? 'admin.businesses.index' : 'businesses.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" type="submit">{{ $businessId === null ? __('Create business') : __('Save changes') }}</flux:button>
        </div>
    </form>
</div>
