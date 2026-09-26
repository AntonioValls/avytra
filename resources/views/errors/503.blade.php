<!DOCTYPE html>
{{-- Maintenance page (php artisan down). Self-contained on purpose: no Vite, Livewire or Flux, so it renders even when the build or the database are unavailable. --}}
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ __('Back in a moment') }} · AVYTRA</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #F5F7FA; color: #101828; font-family: Lato, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        main { max-width: 32rem; margin: 1.5rem; padding: 2.5rem; background: #fff; border: 1px solid #E4E7EC; border-radius: 24px; text-align: center; }
        .logo { display: inline-flex; align-items: center; gap: .5rem; font-weight: 900; font-size: 1.25rem; letter-spacing: .02em; }
        .logo svg { width: 1.5rem; height: 1.5rem; }
        h1 { font-size: 1.75rem; font-weight: 900; line-height: 1.2; margin: 1.5rem 0 .75rem; }
        p { color: #667085; line-height: 1.6; margin: 0; }
        .mark { display: inline-block; margin-top: 1.5rem; padding: .375rem .75rem; border-radius: 12px; background: #B8F34A; color: #101828; font-size: .75rem; font-weight: 700; }
    </style>
</head>
<body>
    <main>
        <span class="logo">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 3 21h4.2l4.8-10.4L16.8 21H21L12 3Z" fill="#101828"/><path d="M9.6 21h4.8L12 15.8 9.6 21Z" fill="#B8F34A"/></svg>
            AVYTRA
        </span>
        <h1>{{ __('Back in a moment') }}</h1>
        <p>{{ __('We are updating AVYTRA. Listings and accounts are safe; the site will be available again shortly.') }}</p>
        <span class="mark">{{ __('Businesses that change hands.') }}</span>
    </main>
</body>
</html>
