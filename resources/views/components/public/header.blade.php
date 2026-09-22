<header class="border-b border-zinc-200 bg-white">
    <flux:header container class="h-16">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" :label="__('Open menu')" />

        <x-app-logo wire:navigate />

        <flux:navbar class="ms-8 max-lg:hidden">
            <flux:navbar.item :href="route('listings.index')" :current="request()->routeIs('listings.index', 'categories.show', 'provinces.show', 'listings.online', 'listings.show')" wire:navigate>{{ __('Explore') }}</flux:navbar.item>
            <flux:navbar.item :href="route('listings.online')" :current="request()->routeIs('listings.online')" wire:navigate>{{ __('Online businesses') }}</flux:navbar.item>
            <flux:navbar.item :href="route('publish.landing')" :current="request()->routeIs('publish.landing')" wire:navigate>{{ __('Sell your business') }}</flux:navbar.item>
        </flux:navbar>

        <flux:spacer />

        <flux:navbar class="max-lg:hidden">
            @auth
                <flux:navbar.item icon="squares-2x2" :href="route('dashboard')" wire:navigate>{{ __('Go to panel') }}</flux:navbar.item>
            @else
                <flux:navbar.item :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:navbar.item>
                <flux:button variant="primary" :href="route('register')" wire:navigate class="ms-2">{{ __('Publish a business') }}</flux:button>
            @endauth
        </flux:navbar>
    </flux:header>

    <flux:sidebar collapsible="mobile" sticky class="lg:hidden border-e border-zinc-200 bg-white">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" wire:navigate />
            <flux:sidebar.collapse />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="magnifying-glass" :href="route('listings.index')" wire:navigate>{{ __('Explore') }}</flux:sidebar.item>
            <flux:sidebar.item icon="globe-alt" :href="route('listings.online')" wire:navigate>{{ __('Online businesses') }}</flux:sidebar.item>
            <flux:sidebar.item icon="megaphone" :href="route('publish.landing')" wire:navigate>{{ __('Sell your business') }}</flux:sidebar.item>
            <flux:sidebar.item icon="question-mark-circle" :href="route('how-it-works')" wire:navigate>{{ __('How it works') }}</flux:sidebar.item>
        </flux:sidebar.nav>

        <flux:sidebar.spacer />

        <flux:sidebar.nav>
            @auth
                <flux:sidebar.item icon="squares-2x2" :href="route('dashboard')" wire:navigate>{{ __('Go to panel') }}</flux:sidebar.item>
            @else
                <flux:sidebar.item icon="arrow-right-end-on-rectangle" :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:sidebar.item>
                <flux:sidebar.item icon="plus-circle" :href="route('register')" wire:navigate>{{ __('Publish a business') }}</flux:sidebar.item>
            @endauth
        </flux:sidebar.nav>
    </flux:sidebar>
</header>
