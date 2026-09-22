@php
    /** @var \App\Support\Listings\PublicListingPresenter $listing */
    $revenue = $listing->disclosedFigure(\App\Enums\FinancialMetric::AnnualRevenue);
    $profit = $listing->disclosedFigure(\App\Enums\FinancialMetric::AnnualProfit);
    $online = $listing->onlineProfile();
    $included = $listing->includedItems();
@endphp

{{-- Main column of the listing page. Also used by the wizard preview (step 8) with the same presenter. --}}
<div class="grid gap-3 sm:grid-cols-3">
    <div class="flex flex-col gap-1 rounded-md bg-mist p-4">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate">{{ __('Price') }}</span>
        <x-price :text="$listing->priceText()" :negotiable="$listing->isPriceNegotiable()" />
    </div>
    @if ($revenue)
        <div class="flex flex-col gap-1 rounded-md bg-mist p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate">{{ __('Annual revenue') }}</span>
            <span class="text-lg font-bold text-ink">{{ $revenue }}</span>
        </div>
    @endif
    @if ($profit)
        <div class="flex flex-col gap-1 rounded-md bg-mist p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate">{{ __('Annual profit') }}</span>
            <span class="text-lg font-bold text-ink">{{ $profit }}</span>
        </div>
    @endif
    @if ($listing->employeeRange())
        <div class="flex flex-col gap-1 rounded-md bg-mist p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate">{{ __('Employees') }}</span>
            <span class="text-lg font-bold text-ink">{{ $listing->employeeRange()->label() }}</span>
        </div>
    @endif
    @if ($listing->foundedYear())
        <div class="flex flex-col gap-1 rounded-md bg-mist p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate">{{ __('Founded') }}</span>
            <span class="text-lg font-bold text-ink">{{ $listing->foundedYear() }}</span>
        </div>
    @endif
    @if ($listing->premisesIsRented() !== null)
        <div class="flex flex-col gap-1 rounded-md bg-mist p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate">{{ __('Premises') }}</span>
            <span class="text-lg font-bold text-ink">{{ $listing->premisesIsRented() ? __('Rented') : __('Owned') }}</span>
        </div>
    @endif
</div>

@if ($listing->operationNotes())
    <flux:callout icon="information-circle" variant="secondary">
        <flux:callout.heading>{{ __('Conditions of the operation') }}</flux:callout.heading>
        <flux:callout.text>{{ $listing->operationNotes() }}</flux:callout.text>
    </flux:callout>
@endif

@if ($listing->description())
    <section class="flex flex-col gap-3">
        <flux:heading size="lg" level="2">{{ __('Description') }}</flux:heading>
        <div class="whitespace-pre-line text-base leading-7 text-ink">{{ $listing->description() }}</div>
    </section>
@endif

@if ($listing->highlights() !== [])
    <section class="flex flex-col gap-3">
        <flux:heading size="lg" level="2">{{ __('Highlights') }}</flux:heading>
        <ul class="grid gap-2 sm:grid-cols-2">
            @foreach ($listing->highlights() as $highlight)
                <li class="flex items-start gap-2 rounded-md border border-zinc-200 p-3 text-sm"><flux:icon.check-circle variant="mini" class="mt-0.5 shrink-0 text-ink" /> <span>{{ $highlight }}</span></li>
            @endforeach
        </ul>
    </section>
@endif

@if ($listing->reasonForSale())
    <section class="flex flex-col gap-3">
        <flux:heading size="lg" level="2">{{ __('Reason for sale') }}</flux:heading>
        <flux:text class="text-base leading-7">{{ $listing->reasonForSale() }}</flux:text>
    </section>
@endif

@if ($included !== [] || $listing->includedAssetsNotes())
    <section class="flex flex-col gap-3">
        <flux:heading size="lg" level="2">{{ __('What is included') }}</flux:heading>
        @if ($included !== [])
            <ul class="grid gap-2 sm:grid-cols-2">
                @foreach ($included as $item)
                    <li class="flex items-center gap-2 text-sm">
                        @if ($item['included'])
                            <flux:icon.check variant="mini" class="shrink-0 text-green-600" />
                        @else
                            <flux:icon.x-mark variant="mini" class="shrink-0 text-zinc-400" />
                        @endif
                        <span @class(['text-zinc-500 line-through' => ! $item['included']])>{{ $item['label'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($listing->includedAssetsNotes())
            <flux:text>{{ $listing->includedAssetsNotes() }}</flux:text>
        @endif
    </section>
@endif

@if ($listing->financialMetrics() !== [])
    <section class="flex flex-col gap-3">
        <flux:heading size="lg" level="2">{{ __('Financial figures') }}</flux:heading>
        <dl class="divide-y divide-zinc-200 rounded-md border border-zinc-200">
            @foreach ($listing->financialMetrics() as $row)
                <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                    <dt class="text-slate">{{ $row['metric']->label() }}@if ($row['year']) <span class="text-xs">({{ $row['year'] }})</span>@endif</dt>
                    <dd class="font-semibold text-ink">{{ $row['text'] }}</dd>
                </div>
            @endforeach
        </dl>
        <flux:text size="sm" class="text-slate">{{ __('Figures declared by the seller. Verify them before any decision.') }}</flux:text>
    </section>
@endif

@if ($online !== null)
    <section class="flex flex-col gap-3">
        <flux:heading size="lg" level="2">{{ __('Online business') }}</flux:heading>
        <div class="flex flex-wrap gap-2">
            <flux:badge color="blue">{{ $online['type']->label() }}</flux:badge>
            @if ($online['platform'])
                <flux:badge color="zinc">{{ $online['platform'] }}</flux:badge>
            @endif
            @if ($online['logistics'])
                <flux:badge color="zinc">{{ __('Logistics') }}: {{ $online['logistics']->label() }}</flux:badge>
            @endif
        </div>
        <dl class="divide-y divide-zinc-200 rounded-md border border-zinc-200 text-sm">
            @if ($online['monthly_visits'])
                <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate">{{ __('Monthly visits') }}</dt><dd class="font-semibold text-ink">{{ $online['monthly_visits'] }}</dd></div>
            @endif
            @if ($online['registered_users'] !== null)
                <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate">{{ __('Registered users') }}</dt><dd class="font-semibold text-ink">{{ number_format($online['registered_users'], 0, ',', '.') }}</dd></div>
            @endif
            @if ($online['active_customers'] !== null)
                <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate">{{ __('Active customers') }}</dt><dd class="font-semibold text-ink">{{ number_format($online['active_customers'], 0, ',', '.') }}</dd></div>
            @endif
            @if ($online['monthly_orders'] !== null)
                <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate">{{ __('Monthly orders') }}</dt><dd class="font-semibold text-ink">{{ number_format($online['monthly_orders'], 0, ',', '.') }}</dd></div>
            @endif
            @if ($online['recurring_revenue_percent'] !== null)
                <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate">{{ __('Recurring revenue') }}</dt><dd class="font-semibold text-ink">{{ $online['recurring_revenue_percent'] }} %</dd></div>
            @endif
            @if ($online['domain_registered_year'] !== null)
                <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate">{{ __('Domain registered in') }}</dt><dd class="font-semibold text-ink">{{ $online['domain_registered_year'] }}</dd></div>
            @endif
            @if ($online['has_stock'] !== null)
                <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate">{{ __('Stock') }}</dt><dd class="font-semibold text-ink">{{ $online['has_stock'] ? __('Yes') : __('No') }}</dd></div>
            @endif
            @if ($online['team_included'] !== null)
                <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate">{{ __('Team included') }}</dt><dd class="font-semibold text-ink">{{ $online['team_included'] ? __('Yes') : __('No') }}</dd></div>
            @endif
        </dl>
        @if ($online['acquisition_channels'] !== [])
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-slate">{{ __('Acquisition channels:') }}</span>
                @foreach ($online['acquisition_channels'] as $channel)
                    <flux:badge size="sm" color="zinc">{{ \App\Enums\AcquisitionChannel::tryFrom($channel)?->label() ?? $channel }}</flux:badge>
                @endforeach
            </div>
        @endif
        @if ($online['marketplaces'] !== [])
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-slate">{{ __('Sells on:') }}</span>
                @foreach ($online['marketplaces'] as $marketplace)
                    <flux:badge size="sm" color="zinc">{{ $marketplace }}</flux:badge>
                @endforeach
            </div>
        @endif
        @if ($online['website_url'])
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <a href="{{ $online['website_url'] }}" target="_blank" rel="nofollow noopener ugc" class="inline-flex items-center gap-1 font-semibold text-transfer hover:underline"><flux:icon.globe-alt variant="micro" /> {{ __('Visit the website') }}</a>
                @foreach ($online['social_profiles'] as $profile)
                    <a href="{{ $profile['url'] }}" target="_blank" rel="nofollow noopener ugc" class="inline-flex items-center gap-1 text-transfer hover:underline">{{ $profile['network'] }}</a>
                @endforeach
            </div>
        @else
            <flux:text size="sm" class="text-slate">{{ __('The website is provided on contact.') }}</flux:text>
        @endif
    </section>
@endif

@if ($listing->provinceName())
    <section class="flex flex-col gap-3">
        <flux:heading size="lg" level="2">{{ __('Location') }}</flux:heading>
        <div class="flex flex-col gap-2 rounded-md border border-zinc-200 p-4">
            <span class="inline-flex items-center gap-2 font-semibold text-ink"><flux:icon.map-pin variant="mini" class="text-transfer" /> {{ $listing->locationText() }}</span>
            @if ($listing->locationExplanation())
                <flux:text size="sm" class="text-slate">{{ $listing->locationExplanation() }}</flux:text>
            @endif
            {{-- The map (Phase 5) will read only the derived public point; the address never appears unless "exact". --}}
        </div>
    </section>
@endif
