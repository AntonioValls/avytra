<?php

use App\Actions\Businesses\CreateBusiness;
use App\Actions\Businesses\UpdateBusiness;
use App\Actions\Listings\CreateListingDraft;
use App\Actions\Listings\PublishListing;
use App\Actions\Listings\UpdateListing;
use App\Actions\Locations\SaveBusinessLocation;
use App\Enums\AcquisitionChannel;
use App\Enums\BusinessType;
use App\Enums\ContactMethod;
use App\Enums\Disclosure;
use App\Enums\EmployeeRange;
use App\Enums\FinancialMetric;
use App\Enums\ListingStatus;
use App\Enums\LocationVisibility;
use App\Enums\LogisticsType;
use App\Enums\OnlineBusinessType;
use App\Enums\OperationType;
use App\Enums\PriceDisclosure;
use App\Enums\TechnologyPlatform;
use App\Exceptions\BusinessAlreadyListed;
use App\Exceptions\InvalidListingTransition;
use App\Exceptions\ListingNotPublishable;
use App\Livewire\Forms\BusinessForm;
use App\Livewire\Forms\ListingWizard\CharacteristicsStepForm;
use App\Livewire\Forms\ListingWizard\ContactStepForm;
use App\Livewire\Forms\ListingWizard\EconomicsStepForm;
use App\Livewire\Forms\ListingWizard\OperationStepForm;
use App\Livewire\Forms\ListingWizard\PublishStepForm;
use App\Livewire\Forms\LocationForm;
use App\Livewire\Forms\OnlineProfileForm;
use App\Models\Business;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\User;
use App\Support\Listings\ListingPublishabilityValidator;
use App\Support\Listings\ListingTitleSuggester;
use App\Support\Listings\PublishabilityReport;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Publication wizard (docs/09-dashboard-and-admin.md): eight steps, one screen at a time,
 * persisted in the database when each step is completed so the owner can leave and come back.
 *
 * The listing draft is created as soon as a business is chosen (step 1). When the business
 * is new, both are created together at the end of step 2 (the business needs a name and a sector).
 * Shared with the admin (/admin/publicaciones), where the superadmin also picks the owner.
 * Orchestrates only: authorize → validate → Actions → feedback.
 */
new class extends Component {
    public const int FIRST_STEP = 1;

    public const int LAST_STEP = 8;

    #[Locked]
    public ?int $listingId = null;

    #[Locked]
    public ?int $businessId = null;

    #[Locked]
    public bool $adminContext = false;

    #[Url(as: 'paso', except: 1)]
    public int $step = self::FIRST_STEP;

    /** Step 1 (new listing only): an existing business, or 0 for "a new business". */
    public ?int $selectedBusinessId = null;

    public ?int $ownerUserId = null;

    public string $ownerSearch = '';

    public OperationStepForm $operation;

    public BusinessForm $business;

    public LocationForm $location;

    public OnlineProfileForm $online;

    public CharacteristicsStepForm $characteristics;

    public EconomicsStepForm $economics;

    public ContactStepForm $contact;

    public PublishStepForm $publishing;

    /** @var list<string> */
    public array $publishErrors = [];

    /**
     * $admin comes from the route default set in routes/admin.php.
     */
    public function mount(?Listing $listing = null, bool $admin = false): void
    {
        $this->adminContext = $admin;

        if ($listing === null) {
            $this->authorize('create', Business::class);
            $this->ownerUserId = Auth::id();

            $preselected = request()->integer('empresa');

            if ($preselected > 0) {
                $business = Business::query()->find($preselected);

                if ($business !== null && Auth::user()->can('create', [Listing::class, $business])) {
                    $this->selectedBusinessId = $business->id;
                    $this->ownerUserId = $business->owner_user_id;
                    $this->business->fillFromBusiness($business);
                }
            }

            $this->step = self::FIRST_STEP;
            $this->suggestContact();

            return;
        }

        $this->authorize('update', $listing);

        $listing->load(['business.location', 'business.onlineProfile', 'operationTypes', 'financialMetrics']);

        $this->listingId = $listing->id;
        $this->businessId = $listing->business_id;
        $this->selectedBusinessId = $listing->business_id;
        $this->ownerUserId = $listing->business->owner_user_id;

        $this->fillForms($listing);
        $this->suggestContact();

        $this->step = max(self::FIRST_STEP, min(self::LAST_STEP, $this->step));
    }

    public function rendering(View $view): void
    {
        $view->title($this->listingId === null ? __('New listing') : __('Edit listing'));
    }

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */

    public function next(): void
    {
        if (! $this->saveStep($this->step)) {
            return;
        }

        $this->step = min(self::LAST_STEP, $this->step + 1);
    }

    public function previous(): void
    {
        $this->step = max(self::FIRST_STEP, $this->step - 1);
    }

    /**
     * Free navigation once the draft exists; the current step is saved on the way.
     */
    public function goTo(int $target): void
    {
        $target = max(self::FIRST_STEP, min(self::LAST_STEP, $target));

        if ($target === $this->step) {
            return;
        }

        if (! $this->canJumpTo($target)) {
            return;
        }

        if ($target > $this->step && ! $this->saveStep($this->step)) {
            return;
        }

        $this->step = $target;
    }

    public function saveAndExit(): void
    {
        if (! $this->saveStep($this->step)) {
            return;
        }

        Flux::toast(variant: 'success', text: __('Draft saved. You can continue whenever you want.'));

        $this->redirectRoute($this->adminContext ? 'admin.listings.index' : 'listings.index', navigate: true);
    }

    public function canJumpTo(int $target): bool
    {
        if ($this->listingId !== null) {
            return true;
        }

        return $target <= self::FIRST_STEP;
    }

    /*
    |--------------------------------------------------------------------------
    | Persistence per step
    |--------------------------------------------------------------------------
    */

    private function saveStep(int $step): bool
    {
        $this->resetErrorBag();
        $this->publishErrors = [];

        /** @var User $actor */
        $actor = Auth::user();

        return match ($step) {
            1 => $this->saveOperationStep($actor),
            2 => $this->saveBasicInfoStep($actor),
            3 => $this->saveCharacteristicsStep($actor),
            4 => $this->saveEconomicsStep($actor),
            5 => $this->saveLocationStep($actor),
            6 => $this->saveContactStep($actor),
            7 => true,
            8 => $this->saveTitleStep($actor),
            default => false,
        };
    }

    private function saveOperationStep(User $actor): bool
    {
        $this->operation->validate();

        if ($this->listingId === null && $this->adminContext) {
            $this->validate(['ownerUserId' => ['required', 'integer', Rule::exists('users', 'id')]], [], ['ownerUserId' => __('owner')]);
        }

        $this->validate(['business.business_type' => ['required', Rule::enum(BusinessType::class)]], [], ['business.business_type' => __('business type')]);

        $listing = $this->listing();

        if ($listing !== null) {
            $this->authorize('update', $listing);

            app(UpdateListing::class)->handle($listing, $actor, $this->operation->toAttributes(), $this->operation->operationTypes());
            app(UpdateBusiness::class)->handle($listing->business, $actor, ['business_type' => $this->business->business_type]);

            return true;
        }

        if ($this->selectedBusinessId === null) {
            $this->addError('selectedBusinessId', __('Choose the business you want to publish or create a new one.'));

            return false;
        }

        // A new business is created together with the draft when step 2 gives it a name and a sector.
        if ($this->selectedBusinessId === 0) {
            return true;
        }

        $business = Business::query()->findOrFail($this->selectedBusinessId);

        $this->authorize('create', [Listing::class, $business]);

        try {
            $listing = DB::transaction(function () use ($business, $actor): Listing {
                app(UpdateBusiness::class)->handle($business, $actor, ['business_type' => $this->business->business_type]);

                return app(CreateListingDraft::class)->handle($business, $actor, $this->operation->toAttributes(), $this->operation->operationTypes());
            });
        } catch (BusinessAlreadyListed $exception) {
            $this->addError('selectedBusinessId', $exception->userMessage());

            return false;
        }

        $this->adopt($listing);

        return true;
    }

    private function saveBasicInfoStep(User $actor): bool
    {
        $this->business->validate();

        $listing = $this->listing();

        if ($listing !== null) {
            $this->authorize('update', $listing);

            app(UpdateBusiness::class)->handle($listing->business, $actor, $this->business->toAttributes());

            return true;
        }

        $this->operation->validate();

        $owner = $this->adminContext ? User::query()->findOrFail($this->ownerUserId) : $actor;

        $this->authorize('create', Business::class);

        $listing = DB::transaction(function () use ($owner, $actor): Listing {
            $business = app(CreateBusiness::class)->handle($owner, $actor, $this->business->toAttributes());

            return app(CreateListingDraft::class)->handle($business, $actor, $this->operation->toAttributes(), $this->operation->operationTypes());
        });

        $this->adopt($listing);

        return true;
    }

    private function saveCharacteristicsStep(User $actor): bool
    {
        $listing = $this->requireListing();
        $this->characteristics->validate();

        app(UpdateListing::class)->handle($listing, $actor, $this->characteristics->toAttributes());

        return true;
    }

    private function saveEconomicsStep(User $actor): bool
    {
        $listing = $this->requireListing();
        $this->economics->validate();

        app(UpdateListing::class)->handle($listing, $actor, $this->economics->toAttributes(), null, $this->economics->declaredMetrics());

        return true;
    }

    private function saveLocationStep(User $actor): bool
    {
        $listing = $this->requireListing();
        $business = $listing->business;

        if ($business->requiresLocation()) {
            $this->location->validate();
        }

        if ($business->requiresOnlineProfile()) {
            $this->online->validate();
        }

        DB::transaction(function () use ($business, $actor): void {
            if ($business->requiresLocation()) {
                app(SaveBusinessLocation::class)->handle($business, $this->location->toAttributes());
            }

            if ($business->requiresOnlineProfile()) {
                app(UpdateBusiness::class)->handle($business, $actor, [], $this->online->toAttributes());
            }
        });

        return true;
    }

    private function saveContactStep(User $actor): bool
    {
        $listing = $this->requireListing();
        $this->contact->validate();

        app(UpdateListing::class)->handle($listing, $actor, $this->contact->toAttributes());

        return true;
    }

    private function saveTitleStep(User $actor): bool
    {
        $listing = $this->requireListing();
        $this->publishing->validate();

        app(UpdateListing::class)->handle($listing, $actor, $this->publishing->toAttributes());

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Publishing
    |--------------------------------------------------------------------------
    */

    public function publish(PublishListing $publishListing): void
    {
        $listing = $this->requireListing();

        $this->authorize('publish', $listing);

        if ($this->publishing->title === '') {
            $this->publishing->title = $this->suggestedTitle ?? '';
        }

        if (! $this->saveStep(self::LAST_STEP)) {
            return;
        }

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $publishListing->handle($listing->fresh(), $actor);
        } catch (ListingNotPublishable $exception) {
            $this->publishErrors = $exception->report->messages();
            unset($this->report);

            return;
        } catch (InvalidListingTransition $exception) {
            $this->publishErrors = [$exception->userMessage()];

            return;
        }

        Flux::toast(variant: 'success', text: __('Your listing is now published.'));

        $this->redirectRoute($this->adminContext ? 'admin.listings.index' : 'listings.index', navigate: true);
    }

    public function useSuggestedTitle(): void
    {
        $this->publishing->title = $this->suggestedTitle ?? '';
    }

    /*
    |--------------------------------------------------------------------------
    | Form helpers
    |--------------------------------------------------------------------------
    */

    public function updatedBusinessCategoryId(): void
    {
        $this->business->subcategory_id = null;
    }

    public function updatedLocationProvinceId(): void
    {
        $this->location->municipality_id = null;
    }

    public function updatedSelectedBusinessId(mixed $value): void
    {
        $this->resetErrorBag('selectedBusinessId');

        if ((int) $value > 0) {
            $business = Business::query()->find((int) $value);

            if ($business !== null && Auth::user()->can('create', [Listing::class, $business])) {
                $this->business->fillFromBusiness($business);

                return;
            }
        }

        $this->business->reset();
    }

    public function updatedOwnerUserId(): void
    {
        $this->selectedBusinessId = null;
        $this->business->reset();
    }

    public function addHighlight(): void
    {
        $this->characteristics->addHighlight();
    }

    public function removeHighlight(int $index): void
    {
        $this->characteristics->removeHighlight($index);
    }

    public function addSocialProfile(): void
    {
        $this->online->addSocialProfile();
    }

    public function removeSocialProfile(int $index): void
    {
        $this->online->removeSocialProfile($index);
    }

    /*
    |--------------------------------------------------------------------------
    | Computed data
    |--------------------------------------------------------------------------
    */

    /**
     * Businesses the chosen owner can still publish (no open listing).
     *
     * @return Collection<int, Business>
     */
    #[Computed]
    public function availableBusinesses(): Collection
    {
        if ($this->ownerUserId === null) {
            return new Collection;
        }

        return Business::query()
            ->where('owner_user_id', $this->ownerUserId)
            ->whereDoesntHave('openListing')
            ->orderBy('name')
            ->get();
    }

    /**
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
        if ($this->business->category_id === null) {
            return new Collection;
        }

        return Category::query()->active()->where('parent_id', $this->business->category_id)->orderBy('sort_order')->orderBy('name')->get();
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

    #[Computed]
    public function currentListing(): ?Listing
    {
        if ($this->listingId === null) {
            return null;
        }

        return Listing::query()
            ->with(['business.category', 'business.subcategory', 'business.location.province', 'business.location.municipality', 'business.onlineProfile', 'operationTypes', 'financialMetrics'])
            ->find($this->listingId);
    }

    /**
     * The "ready to publish" check, evaluated with the title as typed in step 8 (or the
     * suggested one), so the checklist matches what publish() will actually save.
     */
    #[Computed]
    public function report(): ?PublishabilityReport
    {
        $listing = $this->currentListing;

        if ($listing === null) {
            return null;
        }

        $candidate = $listing->replicate(['slug'])->setRelations($listing->getRelations());
        $candidate->title = trim($this->publishing->title) !== '' ? trim($this->publishing->title) : $this->suggestedTitle;

        return app(ListingPublishabilityValidator::class)->validate($candidate);
    }

    #[Computed]
    public function suggestedTitle(): ?string
    {
        $listing = $this->currentListing;

        return $listing === null ? null : app(ListingTitleSuggester::class)->suggest($listing);
    }

    /**
     * Metrics offered in step 4 given the business type and what step 3 declared.
     *
     * @return array{featured: list<FinancialMetric>, more: list<FinancialMetric>}
     */
    #[Computed]
    public function metricGroups(): array
    {
        $type = $this->business->type();
        $groups = ['featured' => [], 'more' => []];

        foreach (FinancialMetric::cases() as $metric) {
            if (! $metric->appliesTo($type, $this->characteristics->includesStock(), $this->characteristics->premisesIsRented())) {
                continue;
            }

            $groups[$metric->isFeatured() ? 'featured' : 'more'][] = $metric;
        }

        return $groups;
    }

    /**
     * @return array<int, string>
     */
    public function stepLabels(): array
    {
        return [
            1 => __('Business and operation'),
            2 => __('Basic information'),
            3 => __('Characteristics'),
            4 => __('Financial information'),
            5 => $this->business->type() === BusinessType::Online ? __('Online business') : __('Location'),
            6 => __('Contact'),
            7 => __('Images'),
            8 => __('Preview and publish'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function listing(): ?Listing
    {
        return $this->listingId === null ? null : Listing::query()->with(['business', 'operationTypes'])->findOrFail($this->listingId);
    }

    private function requireListing(): Listing
    {
        $listing = $this->listing();

        abort_if($listing === null, 404);

        $this->authorize('update', $listing);

        return $listing;
    }

    private function adopt(Listing $listing): void
    {
        $this->listingId = $listing->id;
        $this->businessId = $listing->business_id;
        $this->selectedBusinessId = $listing->business_id;

        unset($this->currentListing, $this->report, $this->suggestedTitle, $this->availableBusinesses);
    }

    private function fillForms(Listing $listing): void
    {
        $business = $listing->business;

        $this->operation->fillFromListing($listing);
        $this->business->fillFromBusiness($business);

        if ($business->location) {
            $this->location->fillFromLocation($business->location);
        }

        if ($business->onlineProfile) {
            $this->online->fillFromOnlineProfile($business->onlineProfile);
        }

        $this->characteristics->fillFromListing($listing);
        $this->economics->fillFromListing($listing);
        $this->contact->fillFromListing($listing);
        $this->publishing->fillFromListing($listing);
    }

    /**
     * Owners see their own name and email as an editable suggestion; the superadmin never does.
     */
    private function suggestContact(): void
    {
        if ($this->adminContext || ! $this->contact->isEmpty()) {
            return;
        }

        /** @var User $user */
        $user = Auth::user();

        $this->contact->suggestFromUser($user);
    }
}; ?>

@php
    $firstStep = $this::FIRST_STEP;
    $lastStep = $this::LAST_STEP;
    $labels = $this->stepLabels();
    $listing = $this->currentListing;
    $type = $business->type();
    $isPublished = $listing !== null && $listing->status !== ListingStatus::Draft;
@endphp

<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl" level="1">{{ $listingId === null ? __('New listing') : __('Edit listing') }}</flux:heading>
        <flux:text>{{ __('One step at a time. Everything is saved when you move on, so you can leave and continue later.') }}</flux:text>
    </div>

    {{-- Progress: a bar on every screen, the list of steps on large screens. --}}
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between gap-4 text-sm">
            <span class="font-semibold text-ink dark:text-white">{{ __('Step :current of :total · :label', ['current' => $step, 'total' => $lastStep, 'label' => $labels[$step]]) }}</span>
            @if ($isPublished)
                <flux:badge size="sm" :color="$listing->status->badgeColor()">{{ $listing->status->label() }}</flux:badge>
            @endif
        </div>
        <flux:progress :value="$step" :max="$lastStep" />

        <ol class="hidden gap-1 lg:grid lg:grid-cols-8">
            @foreach ($labels as $number => $label)
                @php $reachable = $this->canJumpTo($number); @endphp
                <li wire:key="step-tab-{{ $number }}">
                    <button
                        type="button"
                        wire:click="goTo({{ $number }})"
                        @disabled(! $reachable)
                        @class([
                            'flex w-full flex-col gap-1 rounded-md px-2 py-2 text-start text-xs transition',
                            'bg-mist text-ink dark:bg-zinc-800 dark:text-white' => $number === $step,
                            'text-slate hover:bg-mist dark:hover:bg-zinc-800' => $number !== $step && $reachable,
                            'cursor-default text-zinc-400' => ! $reachable,
                        ])
                    >
                        <span class="font-semibold">{{ $number }}</span>
                        <span class="leading-tight">{{ $label }}</span>
                    </button>
                </li>
            @endforeach
        </ol>
    </div>

    <form wire:submit="next" class="flex max-w-3xl flex-col gap-8">
        {{-- ------------------------------------------------------------ Step 1 --}}
        @if ($step === 1)
            <section class="flex flex-col gap-6">
                @if ($listingId === null)
                    @if ($adminContext)
                        <flux:select
                            wire:model.live="ownerUserId"
                            variant="listbox"
                            searchable
                            :filter="false"
                            :label="__('Owner')"
                            :description="__('The user who will own the business and the listing. You stay recorded as its creator.')"
                            :placeholder="__('Choose a user')"
                        >
                            <x-slot name="search">
                                <flux:select.search wire:model.live.debounce.300ms="ownerSearch" :placeholder="__('Search by name or email')" />
                            </x-slot>

                            @foreach ($this->ownerOptions as $user)
                                <flux:select.option :value="$user->id" wire:key="owner-{{ $user->id }}">{{ $user->name }} · {{ $user->email }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif

                    <flux:field>
                        <flux:label>{{ __('Which business do you want to publish?') }}</flux:label>
                        <flux:description>{{ __('Businesses that already have a listing in progress are not shown.') }}</flux:description>
                        <flux:radio.group wire:model.live="selectedBusinessId" variant="cards" class="grid grid-cols-1 sm:grid-cols-2">
                            @foreach ($this->availableBusinesses as $candidate)
                                <flux:radio :value="$candidate->id" :label="$candidate->name" :description="$candidate->business_type->label()" wire:key="candidate-{{ $candidate->id }}" />
                            @endforeach
                            <flux:radio :value="0" :label="__('A new business')" :description="__('You will fill in its data in the next step.')" icon="plus" />
                        </flux:radio.group>
                        <flux:error name="selectedBusinessId" />
                    </flux:field>
                @else
                    <flux:callout icon="building-storefront" variant="secondary">
                        <flux:callout.heading>{{ $listing?->business->name }}</flux:callout.heading>
                        <flux:callout.text>{{ __('The business data is edited in steps 2 and 5.') }}</flux:callout.text>
                    </flux:callout>
                @endif

                @if ($selectedBusinessId !== null || $listingId !== null)
                    <flux:radio.group wire:model.live="business.business_type" variant="cards" :label="__('Business type')" class="max-sm:flex-col">
                        @foreach (BusinessType::cases() as $businessType)
                            <flux:radio :value="$businessType->value" :label="$businessType->label()" :description="$businessType->description()" :icon="$businessType->icon()" />
                        @endforeach
                    </flux:radio.group>

                    <flux:checkbox.group wire:model.live="operation.operation_types" :label="__('What do you offer?')" :description="__('Choose everything that applies. Buyers see a single listing with all the options.')" variant="cards" class="grid grid-cols-1 sm:grid-cols-2">
                        @foreach (OperationType::cases() as $operationType)
                            <flux:checkbox :value="$operationType->value" :label="$operationType->label()" :description="$operationType->description()" />
                        @endforeach
                    </flux:checkbox.group>

                    @if (count($operation->operation_types) > 1)
                        <flux:radio.group wire:model.live="operation.primary_operation_type" :label="__('Main operation')" :description="__('The one shown first and used in the suggested title.')">
                            @foreach ($operation->operationTypes() as $offered)
                                <flux:radio :value="$offered->value" :label="$offered->label()" wire:key="primary-{{ $offered->value }}" />
                            @endforeach
                        </flux:radio.group>
                    @endif

                    @if ($operation->offersPartialOperation())
                        <div class="grid gap-6 sm:grid-cols-[10rem_1fr]">
                            @if ($operation->primary()?->allowsStake())
                                <flux:input wire:model="operation.stake_percent" type="number" min="1" max="100" :label="__('Percentage offered')" />
                            @endif
                            <flux:input wire:model="operation.operation_notes" :label="__('Conditions of the operation')" :description="__('For example: “Partner with a sales profile wanted” or “Minimum 30 %”.')" maxlength="500" />
                        </div>
                    @endif
                @endif
            </section>
        @endif

        {{-- ------------------------------------------------------------ Step 2 --}}
        @if ($step === 2)
            <section class="flex flex-col gap-6">
                <flux:input
                    wire:model="business.name"
                    :label="__('Trade name')"
                    :description="__('It can be generic (for example, “Bakery in the centre of Castellón”) if you prefer not to reveal the brand.')"
                    maxlength="120"
                />

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:select wire:model.live="business.category_id" variant="listbox" searchable :label="__('Sector')" :placeholder="__('Choose a sector')">
                        @foreach ($this->sectors as $sector)
                            <flux:select.option :value="$sector->id" wire:key="sector-{{ $sector->id }}">{{ $sector->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select
                        wire:model="business.subcategory_id"
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

                <flux:input wire:model="business.tagline" :label="__('Short description')" :description="__('One sentence, up to 160 characters. Used on cards and search results.')" maxlength="160" />

                <flux:textarea
                    wire:model="business.description"
                    :label="__('Description')"
                    :description="__('Plain text with paragraphs. At least :min characters are required to publish.', ['min' => config('avytra.limits.description_min_length_to_publish')])"
                    rows="8"
                />

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:input wire:model="business.founded_year" type="number" :label="__('Founding year')" min="1800" :max="now()->year" />

                    <flux:select wire:model="business.employee_range" :label="__('Employees')" :placeholder="__('Choose a range')">
                        @foreach (EmployeeRange::cases() as $range)
                            <flux:select.option :value="$range->value">{{ $range->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:accordion transition>
                    <flux:accordion.item :heading="__('Legal and web details (optional)')">
                        <div class="flex flex-col gap-6 pt-4">
                            <flux:text>{{ __('Private unless you decide otherwise. The legal name is never published.') }}</flux:text>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <flux:input wire:model="business.legal_name" :label="__('Legal name')" :badge="__('Private')" maxlength="160" />
                                <flux:select wire:model="business.legal_form" :label="__('Legal form')" :placeholder="__('Choose a legal form')">
                                    @foreach (\App\Enums\LegalForm::cases() as $legalForm)
                                        <flux:select.option :value="$legalForm->value">{{ $legalForm->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                            <flux:switch wire:model="business.show_legal_form" :label="__('Show the legal form publicly')" />
                            <div class="grid gap-6 sm:grid-cols-2">
                                <flux:input wire:model="business.website_url" type="url" :label="__('Website')" placeholder="https://" maxlength="255" />
                                <flux:select wire:model="business.website_visibility" :label="__('Website visibility')">
                                    @foreach (\App\Enums\WebsiteVisibility::cases() as $visibility)
                                        <flux:select.option :value="$visibility->value">{{ $visibility->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        </div>
                    </flux:accordion.item>
                </flux:accordion>
            </section>
        @endif

        {{-- ------------------------------------------------------------ Step 3 --}}
        @if ($step === 3)
            <section class="flex flex-col gap-6">
                <flux:textarea
                    wire:model="characteristics.reason_for_sale"
                    :label="__('Reason for sale')"
                    :description="__('Optional, but it builds a lot of trust: retirement, change of city, new project…')"
                    rows="3"
                    maxlength="500"
                />

                <div class="flex flex-col gap-3">
                    <flux:label>{{ __('Highlights') }}</flux:label>
                    <flux:description>{{ __('Up to :max short sentences buyers should not miss.', ['max' => config('avytra.limits.highlights_max')]) }}</flux:description>

                    @foreach ($characteristics->highlights as $index => $highlight)
                        <div class="flex gap-3" wire:key="highlight-{{ $index }}">
                            <flux:input wire:model="characteristics.highlights.{{ $index }}" :maxlength="config('avytra.limits.highlight_max_length')" class="flex-1" />
                            <flux:button type="button" variant="ghost" icon="trash" wire:click="removeHighlight({{ $index }})" :aria-label="__('Remove')" />
                        </div>
                    @endforeach
                    <flux:error name="characteristics.highlights" />

                    @if (count($characteristics->highlights) < config('avytra.limits.highlights_max'))
                        <div>
                            <flux:button type="button" size="sm" icon="plus" wire:click="addHighlight">{{ __('Add highlight') }}</flux:button>
                        </div>
                    @endif
                </div>

                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1">
                        <flux:label>{{ __('What is included') }}</flux:label>
                        <flux:description>{{ __('Only what you indicate is shown.') }}</flux:description>
                    </div>

                    @foreach ([
                        'includes_stock' => __('Stock'),
                        'includes_equipment' => __('Machinery and equipment'),
                        'includes_property' => __('The property (premises owned)'),
                        'includes_staff' => __('The team stays'),
                        'includes_intellectual_property' => __('Brand and intellectual property'),
                    ] as $field => $label)
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between" wire:key="include-{{ $field }}">
                            <span class="text-sm text-ink dark:text-white">{{ $label }}</span>
                            <flux:radio.group wire:model.live="characteristics.{{ $field }}" variant="segmented" size="sm">
                                <flux:radio value="yes" :label="__('Yes')" />
                                <flux:radio value="no" :label="__('No')" />
                                <flux:radio value="" :label="__('Not indicated')" />
                            </flux:radio.group>
                        </div>
                    @endforeach

                    <flux:textarea wire:model="characteristics.included_assets_notes" :label="__('Details of the included assets')" rows="3" maxlength="2000" />
                </div>

                @if ($type->requiresLocation())
                    <flux:radio.group wire:model.live="characteristics.premises_is_rented" variant="segmented" :label="__('Premises')">
                        <flux:radio value="yes" :label="__('Rented')" />
                        <flux:radio value="no" :label="__('Owned')" />
                        <flux:radio value="" :label="__('Not indicated')" />
                    </flux:radio.group>
                @endif
            </section>
        @endif

        {{-- ------------------------------------------------------------ Step 4 --}}
        @if ($step === 4)
            <section class="flex flex-col gap-6">
                <flux:radio.group wire:model.live="economics.price_disclosure" variant="cards" :label="__('Asking price')" class="max-sm:flex-col">
                    @foreach (PriceDisclosure::cases() as $disclosure)
                        <flux:radio :value="$disclosure->value" :label="$disclosure->label()" :description="$disclosure->description()" />
                    @endforeach
                </flux:radio.group>

                @if ($economics->price_disclosure === PriceDisclosure::Exact->value)
                    <flux:input wire:model="economics.asking_price" type="number" min="0" step="1" :label="__('Asking price')" class="sm:max-w-xs">
                        <x-slot name="iconTrailing">
                            <span class="pe-3 text-sm text-slate">€</span>
                        </x-slot>
                    </flux:input>
                @elseif ($economics->price_disclosure === PriceDisclosure::Range->value)
                    <div class="grid gap-6 sm:grid-cols-2">
                        <flux:input wire:model="economics.asking_price_min" type="number" min="0" step="1" :label="__('Minimum price')" />
                        <flux:input wire:model="economics.asking_price_max" type="number" min="0" step="1" :label="__('Maximum price')" />
                    </div>
                @endif

                @if ($economics->price_disclosure !== PriceDisclosure::OnRequest->value)
                    <flux:switch wire:model="economics.is_price_negotiable" :label="__('The price is negotiable')" />
                @endif

                <flux:separator />

                <div class="flex flex-col gap-1">
                    <flux:heading size="lg" level="2">{{ __('Financial figures') }}</flux:heading>
                    <flux:text>{{ __('All optional. For each figure you decide whether to show it exactly, as a range or only on request.') }}</flux:text>
                </div>

                @foreach ($this->metricGroups['featured'] as $metric)
                    @include('partials.wizard-metric', ['metric' => $metric])
                @endforeach

                @if ($this->metricGroups['more'] !== [])
                    <flux:accordion transition>
                        <flux:accordion.item :heading="__('Add more financial figures')">
                            <div class="flex flex-col gap-6 pt-4">
                                @foreach ($this->metricGroups['more'] as $metric)
                                    @include('partials.wizard-metric', ['metric' => $metric])
                                @endforeach
                            </div>
                        </flux:accordion.item>
                    </flux:accordion>
                @endif
            </section>
        @endif

        {{-- ------------------------------------------------------------ Step 5 --}}
        @if ($step === 5)
            <section class="flex flex-col gap-10">
                @if ($type->requiresLocation())
                    <div class="flex flex-col gap-6">
                        <div class="flex flex-col gap-1">
                            <flux:heading size="lg" level="2">{{ __('Location') }}</flux:heading>
                            <flux:text>{{ __('Where the premises are. You decide how much is shown publicly.') }}</flux:text>
                        </div>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <flux:select wire:model.live="location.province_id" variant="listbox" searchable :label="__('Province')" :placeholder="__('Choose a province')">
                                @foreach ($this->provinces as $province)
                                    <flux:select.option :value="$province->id" wire:key="province-{{ $province->id }}">{{ $province->name }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:select
                                wire:model="location.municipality_id"
                                variant="listbox"
                                searchable
                                :label="__('Municipality')"
                                :placeholder="$this->municipalities->isEmpty() ? __('Choose a province first') : __('Choose a municipality')"
                                :disabled="$this->municipalities->isEmpty()"
                            >
                                @foreach ($this->municipalities as $municipality)
                                    <flux:select.option :value="$municipality->id" wire:key="municipality-{{ $municipality->id }}">{{ $municipality->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>

                        <div class="grid gap-6 sm:grid-cols-[1fr_10rem]">
                            <flux:input wire:model="location.address_line" :label="__('Address')" :badge="__('Private')" :description="__('Shown only if you choose “Exact address”.')" maxlength="255" />
                            <flux:input wire:model="location.postal_code" :label="__('Postal code')" :badge="__('Private')" maxlength="10" />
                        </div>

                        <flux:radio.group wire:model="location.location_visibility" :label="__('Location visibility')">
                            @foreach (LocationVisibility::cases() as $visibility)
                                <flux:radio :value="$visibility->value" :label="$visibility->label()" :description="$visibility->description()" />
                            @endforeach
                        </flux:radio.group>

                        <flux:callout icon="map" variant="secondary">
                            <flux:callout.text>{{ __('The map to place the exact point of the premises will be available soon. Until then, the public map shows the municipality area.') }}</flux:callout.text>
                        </flux:callout>
                    </div>
                @endif

                @if ($type->requiresOnlineProfile())
                    <div class="flex flex-col gap-6">
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
                            <flux:select wire:model.live="online.technology_platform" :label="__('Technology platform')" :placeholder="__('Choose a platform')">
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
                            <flux:select wire:model="online.monthly_visits_disclosure" :label="__('Visits disclosure')">
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
                                        <flux:select wire:model="online.logistics_type" :label="__('Logistics')" :placeholder="__('Choose an option')">
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

                                    <flux:input wire:model="online.sells_on_marketplaces" :label="__('Marketplaces where it sells')" :description="__('Separate with commas: Amazon, Etsy, eBay…')" maxlength="255" />

                                    <div class="flex flex-col gap-3">
                                        <flux:label>{{ __('Social profiles') }}</flux:label>
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
                    </div>
                @endif
            </section>
        @endif

        {{-- ------------------------------------------------------------ Step 6 --}}
        @if ($step === 6)
            <section class="flex flex-col gap-6">
                <flux:callout icon="eye" variant="secondary">
                    <flux:callout.text>{{ __('Everything you write here will be shown on your listing. Leave empty any channel you do not want to share.') }}</flux:callout.text>
                </flux:callout>

                <flux:input wire:model="contact.contact_name" :label="__('Contact name')" :description="__('Who buyers will talk to. It does not have to be you.')" maxlength="80" />

                <flux:radio.group wire:model.live="contact.preferred_contact_method" :label="__('Preferred contact method')" variant="cards" class="grid grid-cols-2 sm:grid-cols-3">
                    @foreach (ContactMethod::cases() as $method)
                        <flux:radio :value="$method->value" :label="$method->label()" :icon="$method->icon()" />
                    @endforeach
                </flux:radio.group>

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:input wire:model="contact.contact_email" type="email" :label="__('Contact email')" maxlength="255" />
                    <flux:phone wire:model="contact.contact_phone" country="es" :country-order="['es', 'pt', 'fr', 'ad']" :label="__('Contact phone')" />
                </div>

                <div class="flex flex-col gap-3">
                    <flux:switch wire:model.live="contact.whatsapp_same_as_phone" :label="__('WhatsApp on the same number')" />
                    @unless ($contact->whatsapp_same_as_phone)
                        <flux:phone wire:model="contact.contact_whatsapp" country="es" :country-order="['es', 'pt', 'fr', 'ad']" :label="__('WhatsApp number')" class="sm:max-w-sm" />
                    @endunless
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:input wire:model="contact.contact_website_url" type="url" :label="__('Contact website')" placeholder="https://" maxlength="255" />
                    <flux:input wire:model="contact.contact_form_url" type="url" :label="__('External form')" placeholder="https://" maxlength="255" />
                </div>

                <flux:input wire:model="contact.contact_other" :label="__('Other way to contact')" :description="__('For example: “Ask for Marta at the premises”.')" maxlength="255" />
                <flux:input wire:model="contact.contact_notes" :label="__('Contact notes')" :description="__('Timetable or instructions: “Call from 9 to 14h”.')" maxlength="255" />

                <flux:card class="flex flex-col gap-3 bg-mist dark:bg-zinc-900">
                    <flux:heading size="sm">{{ __('How buyers will see it') }}</flux:heading>
                    <div class="flex flex-col gap-2">
                        <span class="font-semibold text-ink dark:text-white">{{ $contact->contact_name !== '' ? $contact->contact_name : __('Contact name') }}</span>
                        @if ($contact->preferred())
                            <div>
                                <flux:button variant="primary" size="sm" :icon="$contact->preferred()->icon()" type="button">{{ $contact->preferred()->actionLabel() }}</flux:button>
                            </div>
                        @endif
                        @if ($contact->contact_notes !== '')
                            <flux:text size="sm">{{ $contact->contact_notes }}</flux:text>
                        @endif
                        <flux:text size="sm" class="text-slate">{{ __('Phone numbers and emails are revealed only after a click, to hinder automated harvesting.') }}</flux:text>
                    </div>
                </flux:card>
            </section>
        @endif

        {{-- ------------------------------------------------------------ Step 7 --}}
        @if ($step === 7)
            <section class="flex flex-col gap-6">
                <x-empty-state icon="photo" :heading="__('Images arrive soon.')" :text="__('Cover, logo and gallery will be available in an upcoming update. Your listing can be published without images: a brand placeholder is shown meanwhile.')" />
            </section>
        @endif

        {{-- ------------------------------------------------------------ Step 8 --}}
        @if ($step === 8 && $listing !== null)
            <section class="flex flex-col gap-8">
                <div class="flex flex-col gap-3">
                    <flux:input
                        wire:model="publishing.title"
                        :label="__('Title of the listing')"
                        :description="__('Up to :max characters. It is the headline buyers see and the base of the public URL.', ['max' => config('avytra.limits.title_max_length')])"
                        :maxlength="config('avytra.limits.title_max_length')"
                    />
                    @if ($this->suggestedTitle && $this->suggestedTitle !== $publishing->title)
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="text-slate">{{ __('Suggestion: “:title”', ['title' => $this->suggestedTitle]) }}</span>
                            <flux:button type="button" size="xs" variant="ghost" wire:click="useSuggestedTitle">{{ __('Use it') }}</flux:button>
                        </div>
                    @endif
                </div>

                @if ($publishErrors !== [])
                    <flux:callout icon="exclamation-triangle" variant="danger">
                        <flux:callout.heading>{{ __('The listing cannot be published yet.') }}</flux:callout.heading>
                        <flux:callout.text>
                            <ul class="list-disc ps-4">
                                @foreach ($publishErrors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </flux:callout.text>
                    </flux:callout>
                @endif

                @if ($this->report?->fails())
                    <flux:callout icon="clipboard-document-check" variant="warning">
                        <flux:callout.heading>{{ __('What is still missing') }}</flux:callout.heading>
                        <flux:callout.text>
                            <ul class="flex flex-col gap-1">
                                @foreach ($this->report->issues as $issue)
                                    <li class="flex flex-wrap items-center gap-2">
                                        <span>{{ $issue->message }}</span>
                                        @if ($issue->step !== $lastStep)
                                            <flux:button type="button" size="xs" variant="ghost" wire:click="goTo({{ $issue->step }})">{{ __('Go to step :step', ['step' => $issue->step]) }}</flux:button>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </flux:callout.text>
                    </flux:callout>
                @else
                    <flux:callout icon="check-circle" variant="success">
                        <flux:callout.text>{{ $isPublished ? __('Everything is in order. Save to apply your changes.') : __('Everything is in order. You can publish now.') }}</flux:callout.text>
                    </flux:callout>
                @endif

                {{-- Preview: how the main data will read on the public page. Images and map arrive in later phases. --}}
                <flux:card class="flex flex-col gap-6">
                    <div class="flex flex-col gap-3">
                        <div class="flex flex-wrap gap-2">
                            @foreach ($listing->offeredOperationTypes() as $offered)
                                <flux:badge size="sm" :color="$offered->badgeColor()" wire:key="preview-op-{{ $offered->value }}">{{ $offered->label() }}</flux:badge>
                            @endforeach
                            @if ($listing->business->business_type !== BusinessType::Physical)
                                <flux:badge size="sm" :color="$listing->business->business_type->badgeColor()">{{ $listing->business->business_type->label() }}</flux:badge>
                            @endif
                        </div>
                        <flux:heading size="xl" level="2">{{ $publishing->title !== '' ? $publishing->title : ($this->suggestedTitle ?? __('Untitled listing')) }}</flux:heading>
                        <flux:text>
                            {{ $listing->business->category?->name }}
                            @if ($listing->business->location)
                                · {{ $listing->business->location->municipality?->name ?? $listing->business->location->province->name }}
                            @else
                                · {{ __('Online') }}
                            @endif
                        </flux:text>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="flex flex-col gap-1 rounded-md bg-mist p-4 dark:bg-zinc-900">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate">{{ __('Price') }}</span>
                            <x-price :listing="$listing" />
                        </div>
                        @if ($listing->business->employee_range)
                            <div class="flex flex-col gap-1 rounded-md bg-mist p-4 dark:bg-zinc-900">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate">{{ __('Employees') }}</span>
                                <span class="font-semibold text-ink dark:text-white">{{ $listing->business->employee_range->label() }}</span>
                            </div>
                        @endif
                        @if ($listing->business->founded_year)
                            <div class="flex flex-col gap-1 rounded-md bg-mist p-4 dark:bg-zinc-900">
                                <span class="text-xs font-semibold uppercase tracking-wider text-slate">{{ __('Founded') }}</span>
                                <span class="font-semibold text-ink dark:text-white">{{ $listing->business->founded_year }}</span>
                            </div>
                        @endif
                    </div>

                    @if ($listing->business->description)
                        <div class="flex flex-col gap-2">
                            <flux:heading size="sm">{{ __('Description') }}</flux:heading>
                            <div class="whitespace-pre-line text-sm text-ink dark:text-zinc-200">{{ $listing->business->description }}</div>
                        </div>
                    @endif

                    @if ($listing->highlights)
                        <div class="flex flex-col gap-2">
                            <flux:heading size="sm">{{ __('Highlights') }}</flux:heading>
                            <ul class="flex flex-col gap-1 text-sm">
                                @foreach ($listing->highlights as $highlight)
                                    <li class="flex items-start gap-2"><flux:icon.check variant="micro" class="mt-0.5 shrink-0 text-ink dark:text-lime" /> {{ $highlight }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($listing->reason_for_sale)
                        <div class="flex flex-col gap-2">
                            <flux:heading size="sm">{{ __('Reason for sale') }}</flux:heading>
                            <flux:text>{{ $listing->reason_for_sale }}</flux:text>
                        </div>
                    @endif

                    @if ($listing->financialMetrics->isNotEmpty())
                        <div class="flex flex-col gap-2">
                            <flux:heading size="sm">{{ __('Financial figures') }}</flux:heading>
                            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                                @foreach ($listing->financialMetrics as $metric)
                                    @if ($metric->disclosure !== Disclosure::Hidden)
                                        <div class="flex justify-between gap-4 border-b border-zinc-100 py-1 dark:border-zinc-800" wire:key="preview-metric-{{ $metric->id }}">
                                            <dt class="text-slate">{{ $metric->metric->label() }}</dt>
                                            <dd class="font-semibold text-ink dark:text-white">
                                                @switch($metric->disclosure)
                                                    @case(Disclosure::Exact)
                                                        {{ \Illuminate\Support\Number::currency((int) $metric->amount, $metric->currency, locale: 'es', precision: 0) }}
                                                        @break
                                                    @case(Disclosure::Range)
                                                        {{ \Illuminate\Support\Number::currency((int) $metric->amount_min, $metric->currency, locale: 'es', precision: 0) }} – {{ \Illuminate\Support\Number::currency((int) $metric->amount_max, $metric->currency, locale: 'es', precision: 0) }}
                                                        @break
                                                    @default
                                                        {{ __('On request') }}
                                                @endswitch
                                            </dd>
                                        </div>
                                    @endif
                                @endforeach
                            </dl>
                        </div>
                    @endif

                    <div class="flex flex-col gap-2 rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading size="sm">{{ __('Contact the owner') }}</flux:heading>
                        <span class="text-sm font-semibold text-ink dark:text-white">{{ $listing->contact_name ?? '—' }}</span>
                        @if ($listing->preferred_contact_method)
                            <div>
                                <flux:button variant="primary" size="sm" type="button" :icon="$listing->preferred_contact_method->icon()">{{ $listing->preferred_contact_method->actionLabel() }}</flux:button>
                            </div>
                        @endif
                    </div>
                </flux:card>
            </section>
        @endif

        {{-- ------------------------------------------------------------ Navigation --}}
        <div class="sticky bottom-0 -mx-4 flex flex-col-reverse gap-3 border-t border-zinc-200 bg-white/95 px-4 py-4 backdrop-blur sm:mx-0 sm:flex-row sm:items-center sm:justify-between sm:px-0 dark:border-zinc-700 dark:bg-zinc-800/95">
            <div class="flex gap-3">
                @if ($step > $firstStep)
                    <flux:button type="button" icon="arrow-left" wire:click="previous">{{ __('Previous') }}</flux:button>
                @endif
                @if ($listingId !== null || $step > $firstStep)
                    <flux:button type="button" variant="ghost" wire:click="saveAndExit">{{ __('Save and exit') }}</flux:button>
                @else
                    <flux:button type="button" variant="ghost" :href="route($adminContext ? 'admin.listings.index' : 'listings.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
                @endif
            </div>

            <div class="flex gap-3 sm:justify-end">
                @if ($step < $lastStep)
                    <flux:button type="submit" variant="primary" icon-trailing="arrow-right">{{ __('Next') }}</flux:button>
                @elseif ($isPublished)
                    <flux:button type="button" variant="primary" icon="check" wire:click="saveAndExit">{{ __('Save changes') }}</flux:button>
                @else
                    <flux:button type="button" variant="primary" icon="rocket-launch" wire:click="publish" :disabled="$this->report?->fails()">{{ __('Publish') }}</flux:button>
                @endif
            </div>
        </div>
    </form>
</div>
