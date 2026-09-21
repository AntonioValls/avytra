<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['appearance' => false])
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
            <div class="relative hidden h-full flex-col bg-ink p-10 text-white lg:flex">
                <x-app-logo class="relative z-20 text-white" wire:navigate />

                <div class="relative z-20 mt-auto flex flex-col gap-3">
                    <flux:heading size="xl" class="text-white">{{ __('Businesses that change hands.') }}</flux:heading>
                    <flux:text class="text-zinc-300">{{ __('Buy. Sell. Continue.') }}</flux:text>
                </div>
            </div>
            <div class="w-full lg:p-8">
                <div class="mx-auto flex w-full flex-col justify-center gap-6 sm:w-[350px]">
                    <div class="flex justify-center lg:hidden">
                        <x-app-logo wire:navigate />
                    </div>
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
