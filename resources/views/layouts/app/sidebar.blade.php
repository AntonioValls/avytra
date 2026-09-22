@props([
    'title' => null,
    'area' => null,
])

@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    // Livewire pages cannot pass layout props per route, so the area follows the route name.
    $area ??= request()->routeIs('admin.*') ? 'admin' : 'app';
    $isAdminArea = $area === 'admin';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        {{--
            The admin area reuses the same layout but its sidebar is Ink (dark) so the
            context is unmistakable. The "dark" class scopes Flux's dark styles to the
            sidebar only; the content area stays light.
        --}}
        <flux:sidebar
            sticky
            collapsible="mobile"
            @class([
                'border-e',
                'border-zinc-200 bg-mist dark:border-zinc-700 dark:bg-zinc-900' => ! $isAdminArea,
                'dark border-ink !bg-ink' => $isAdminArea,
            ])
        >
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" :href="route('dashboard')" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @if ($isAdminArea)
                <div class="px-2">
                    <flux:badge color="lime" size="sm">{{ __('Administration') }}</flux:badge>
                </div>
            @endif

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="$isAdminArea ? __('Administration') : __('Panel')" class="grid">
                    @if ($isAdminArea)
                        <flux:sidebar.item icon="squares-2x2" :href="route('admin.index')" :current="request()->routeIs('admin.index')" wire:navigate>
                            {{ __('Operational summary') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="building-storefront" :href="route('admin.businesses.index')" :current="request()->routeIs('admin.businesses.*')" wire:navigate>
                            {{ __('Businesses') }}
                        </flux:sidebar.item>
                    @else
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Home') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="building-storefront" :href="route('businesses.index')" :current="request()->routeIs('businesses.*')" wire:navigate>
                            {{ __('My businesses') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                @if ($user->isSuperadmin())
                    @if ($isAdminArea)
                        <flux:sidebar.item icon="arrow-uturn-left" :href="route('dashboard')" wire:navigate>
                            {{ __('Panel') }}
                        </flux:sidebar.item>
                    @else
                        <flux:sidebar.item icon="shield-check" :href="route('admin.index')" wire:navigate>
                            {{ __('Administration') }}
                        </flux:sidebar.item>
                    @endif
                @endif
                <flux:sidebar.item icon="globe-alt" :href="route('home')" wire:navigate>
                    {{ __('Back to site') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="$user->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="$user->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="$user->name"
                                    :initials="$user->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ $user->name }}</flux:heading>
                                    <flux:text class="truncate">{{ $user->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
