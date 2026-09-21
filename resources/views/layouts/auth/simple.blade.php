<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['appearance' => false])
    </head>
    <body class="min-h-screen bg-mist antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-6">
                <div class="flex justify-center">
                    <x-app-logo wire:navigate />
                </div>
                <div class="flex flex-col gap-6 rounded-lg border border-zinc-200 bg-white p-8 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
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
