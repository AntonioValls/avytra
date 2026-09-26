# 13 — Vigencia, recordatorios y pausa automática

## Objetivo

Que ninguna publicación quede abandonada sin que se note, y que ninguna desaparezca por no responder: se pausa y es recuperable con un clic.

## Configuración central (`config/avytra.php`)

```php
return [
    'freshness' => [
        'confirmation_period_days' => env('AVYTRA_CONFIRMATION_PERIOD_DAYS', 60), // pausa automática
        'first_reminder_days'      => env('AVYTRA_FIRST_REMINDER_DAYS', 45),
        'second_reminder_days'     => env('AVYTRA_SECOND_REMINDER_DAYS', 55),
        'confirmation_link_ttl_days' => 20,   // validez de la firma del enlace del email (evita enlaces eternos)
        'sold_visible_days'        => 30,     // días que una vendida sigue siendo pública
    ],
    'contact' => ['reveal_rate_limit_per_hour' => 20],
    'reports' => ['rate_limit_per_hour' => 3],
    'location' => ['jitter_salt' => env('AVYTRA_LOCATION_SALT'), 'approximate_radius_m' => 700],
    'map' => ['style_url' => env('MAP_STYLE_URL', 'https://tiles.openfreemap.org/styles/liberty')],
    'geocoding' => ['driver' => env('GEOCODING_DRIVER', 'null')],
    'support' => ['email' => env('AVYTRA_SUPPORT_EMAIL'), 'phone' => env('AVYTRA_SUPPORT_PHONE')],
];
```

Regla: ningún número de días aparece fuera de este archivo. Los Actions y comandos leen `config('avytra.freshness.*')`. Los tests sobrescriben con `config()->set()` para probar umbrales.

Invariante de configuración: `first_reminder_days < second_reminder_days < confirmation_period_days`. Se valida en un test.

## Línea temporal

```text
día 0        publish / confirm / resume  → last_confirmed_at = now, next_confirmation_at = +60d
día 45       primer aviso  (email "¿Sigue disponible?")        first_reminder_sent_at
día 55       segundo aviso (email "Se pausará en 5 días")      second_reminder_sent_at
día 60       autoExpire → status = expired, expired_at = now    email "Se ha pausado; reactívala con un clic"
             (publicación fuera de resultados; datos intactos)
cualquier día: clic "Sigue disponible" → vuelve a día 0
```

En el panel, desde el día 45 la publicación muestra "Necesita confirmación" (condición derivada, ver [06-listing-lifecycle.md](06-listing-lifecycle.md)).

## Comando y scheduler

Un único comando `php artisan avytra:listings:process-freshness`, programado cada hora en `routes/console.php` (`Schedule::command(...)->hourly()->withoutOverlapping()`). Hace tres pasadas idempotentes:

1. **Primer aviso:** `published` ∧ `last_confirmed_at <= now - first_reminder_days` ∧ `first_reminder_sent_at IS NULL` → despacha `SendListingFreshnessReminder($listing, ReminderStage::First)` y marca `first_reminder_sent_at = now()` **antes** de encolar (evita duplicados si el job se reintenta o el comando se solapa).
2. **Segundo aviso:** ídem con `second_reminder_days` y `second_reminder_sent_at`.
3. **Pausa automática:** `published` ∧ `last_confirmed_at <= now - confirmation_period_days` → `ExpireListing` (Action) → notificación `ListingExpired`.

Selección por `chunkById(200)` sobre índices `(status, last_confirmed_at)`. El comando es ejecutable manualmente y con `--dry-run` para inspección.

Se ejecuta cada hora (no diario) para que el instante de pausa sea predecible dentro de ±1 h y para repartir carga.

## Notificaciones (Laravel Notifications, canal `mail`, en cola)

| Notificación | Destinatario | Contenido |
|---|---|---|
| `ListingFreshnessReminder` (stage first) | Propietario (`business.owner`) | "¿*{título}* sigue disponible? [Sí, sigue disponible] — Si no confirmas, se pausará el {fecha}." |
| `ListingFreshnessReminder` (stage second) | Propietario | Mismo con urgencia moderada y fecha exacta. |
| `ListingExpired` | Propietario | "Hemos pausado *{título}* porque no pudimos confirmar que siga disponible. Tus datos están intactos. [Reactivar publicación]." |
| `ListingSuspended` | Propietario | Motivo y contacto de soporte. |
| `ListingPublished` | Propietario | Confirmación con enlace público y explicación del sistema de vigencia (primera vez). |
| `ListingReportReceived` | Superadmin | Nuevo reporte. |

Idioma: español, plantillas Markdown de Laravel con marca (logo, Ink/Lime), sin lenguaje alarmista ("urgente", "última oportunidad").

El destinatario es siempre el **email de la cuenta del propietario**, no el email de contacto de la publicación (que puede ser de terceros). Si el superadmin creó la cuenta con un email provisional propio, recibe él los avisos y confirma en nombre de la persona.

## Confirmación con sesión iniciada (ADR-007)

El email enlaza a una página del panel: `/panel/publicaciones/{listing}/confirmar` (enlace firmado temporal con `confirmation_link_ttl_days` para que un email antiguo no siga llevando a una acción, y ruta bajo `auth`).

- **Requiere login.** Si el propietario no tiene sesión, ve la pantalla de acceso (con "¿Has olvidado tu contraseña?" y passkeys visibles) y tras entrar aterriza en la página de confirmación (`intended`).
- La página muestra la publicación y un único botón "Sí, sigue disponible" (POST vía Livewire, autorizado con `ListingPolicy::confirm`). Al pulsarlo: "Gracias, tu publicación sigue vigente hasta el {fecha}". Botones secundarios: "Marcar como vendida", "Pausar".
- Funciona tanto para `published` (confirm) como para `expired` (resume). Para `paused` (pausa voluntaria) muestra "Está pausada por ti" con el botón "Reactivar".
- Firma caducada → la misma página sin la acción automática, con explicación; el botón sigue disponible desde el panel.
- Cada confirmación registra `ListingEvent::Confirmed` con `payload.channel = email_link|dashboard|admin` y el `actor_user_id` real.
- Por qué login y no un enlace sin sesión: el actor queda identificado, no hay rutas GET que muten estado y un email reenviado no permite actuar sobre la cuenta. El coste en fricción se mitiga con passkeys, reset de contraseña en un clic y la confirmación en nombre del propietario por el superadmin para cuentas asistidas. Los enlaces de acceso sin contraseña quedan en roadmap si la fricción resulta real.

Además: botón "Sigue disponible" en el panel (siempre visible en publicadas) y acción "Confirmar en nombre del propietario" en admin (con `on_behalf_of_user_id`).

## Fallos y reintentos

- Los jobs de notificación usan `ShouldQueue`, `tries = 3`, backoff exponencial. Si fallan definitivamente van a `failed_jobs`; el flag `*_reminder_sent_at` ya está puesto, así que **no se reenvía automáticamente**: el superadmin ve en `/admin` un listado de "avisos fallidos" (join con `failed_jobs` por payload o, más simple, un `ListingEvent::ReminderFailed` registrado en el `failed()` del job) y puede reenviar manualmente.
- Si el scheduler no corre durante días, al reanudarse procesa todo lo pendiente en una pasada; una publicación puede pasar directamente a `expired` sin avisos previos. Se acepta; el email de pausa explica cómo reactivar. Mitigación: monitorizar el scheduler (heartbeat) en Phase 10.
- Email inválido/rebotado: fuera del MVP (no hay tracking de rebotes). El superadmin puede ver publicaciones caducadas sin reactivar a los 30 días y llamar.

## Duplicados

- Flags `first_reminder_sent_at`/`second_reminder_sent_at` en la fila (reset a `null` al confirmar/reanudar).
- `withoutOverlapping()` en el scheduler.
- Jobs con `ShouldBeUnique` por `listing_id + stage` durante 1 h como segunda barrera.

## Público

En ficha y tarjeta: "Disponibilidad confirmada hace N días" (0 → "hoy", 1 → "ayer"). Nunca se muestra "hace 58 días" sin más: a partir de `first_reminder_days` la ficha sigue mostrándose (estado `published`) pero el texto cambia a "Confirmada hace N días · pendiente de renovación" para ser honestos con el comprador.

## Tests previstos

- Publicar fija `last_confirmed_at` y `next_confirmation_at` según config.
- `process-freshness` envía primer aviso a día 45, no lo repite, envía segundo a día 55, expira a día 60 (con `travel()` y config alterada a valores pequeños para rapidez).
- Confirmar resetea flags y fechas.
- Enlace del email sin sesión redirige a login y, tras entrar, muestra la página de confirmación; un usuario distinto del propietario recibe 403/404.
- Confirmar desde la página registra el evento con el actor correcto; firma caducada no ejecuta nada automáticamente.
- Página sobre `paused` ofrece "Reactivar", no confirma sola.
- Publicación `expired` no aparece en público y sí reaparece tras resume.
- Comando idempotente al ejecutarse dos veces seguidas.

## Implementación (Phase 7)

- **Comando** `App\Console\Commands\ProcessListingFreshness` (`avytra:listings:process-freshness {--dry-run}`), programado en `routes/console.php` con `hourly()->withoutOverlapping()`. Tres pasadas en este orden: primer aviso, segundo aviso, pausa. Cada pasada usa un scope del modelo (`dueForReminder(ReminderStage)`, `dueForExpiration()`) con `chunkById(200)`. Para que una publicación reciba **como máximo un email por ejecución** (scheduler parado varios días), `dueForReminder` excluye las que ya han superado el siguiente umbral: la que está entre 55 y 60 días solo recibe el segundo aviso (y `SendListingFreshnessReminder` marca también `first_reminder_sent_at`), la que supera los 60 se pausa sin avisos. `--dry-run` lista las publicaciones afectadas y no cambia nada.
- **Enum** `App\Enums\ReminderStage` (`first`, `second`) con `days()`, `nextThresholdDays()` y `sentAtColumn()`; los umbrales siguen únicamente en `config/avytra.php` (nuevo `expired_review_days` para el resumen admin y `rate_limits.confirmation_per_hour`).
- **Actions**: `SendListingFreshnessReminder` (escribe el flag `*_reminder_sent_at` y el evento `reminder_sent` con `payload.stage` en la misma transacción **antes** de encolar la notificación) y `ExpireListing` (ahora envía `ListingExpired`). `ResendFailedReminder` reenvía a mano el email que falló (aviso si la publicación está `published`, pausa si está `expired`) con evento `reminder_sent` (`payload.resent = true`) y audit `listing.reminder_resent_by_admin`.
- **Notificaciones** `ListingFreshnessReminder` (dos etapas) y `ListingExpired`: `ShouldQueue`, `tries = 3`, `backoff` 60/300/900. Su método `failed()` registra `ListingEventType::ReminderFailed` (`payload.stage`, `payload.error`) en lugar de reintentar: el flag ya está puesto. No se usa `ShouldBeUnique` (las notificaciones en cola de Laravel no lo soportan); las barreras contra duplicados son el flag escrito antes de encolar y `withoutOverlapping()`.
- **Enlace firmado** `App\Support\Listings\ConfirmationLink::for($listing)` (`temporarySignedRoute` con `confirmation_link_ttl_days`) e `isValid($listing, $request)`, que recalcula la firma sobre la URL canónica de la ruta (no sobre la URL de la petición) para funcionar tras proxies y dentro de las peticiones de Livewire.
- **Página** `pages::listings.confirm` en `/panel/publicaciones/{listing}/confirmar` (`auth`, `verified`, `throttle:confirmation` 30/hora por usuario). Comprueba la firma una vez en `mount()` y la guarda en una propiedad `#[Locked]`; el botón "Sí, sigue disponible" hace `confirm` (publicada) o `resume` (caducada o pausada) con canal `email_link` y exige firma válida (403 si no). Con firma caducada o sin firma muestra "Este enlace ha caducado" y lleva al panel. Secundarios: "Marcar como vendida" (modal) y "Pausar". Estados terminales muestran la descripción del estado.
- **Tema de correo**: `config/mail.php` → `markdown.theme = avytra` y `resources/views/vendor/mail/html/themes/avytra.css` (Ink, Mist, Lime para el botón principal, Transfer Blue para enlaces, radios 12). Solo se sobrescribe el CSS, no los componentes.
- **Panel y admin**: botón "Sigue disponible" visible en la tabla y en las tarjetas de "Mis publicaciones" cuando `needsConfirmation()` (además del menú); resumen operativo `pages::admin.index` con contadores y listas (necesitan confirmación, pausadas automáticamente en `expired_review_days`, avisos no entregados, reportes abiertos, últimas publicaciones y usuarios); filtros `condicion=expired_recently|failed_reminder` en `/admin/publicaciones`; callout "El email no se pudo entregar" con botón "Reenviar" en el detalle admin. Scopes `expiredRecently()` y `withFailedReminder()` (evento `reminder_failed` posterior a `last_confirmed_at`) y `Listing::latestFailedReminder()`.
- **Tests**: `Freshness/ProcessListingFreshnessTest` (secuencia 45/55/60 exactamente una vez con `travel`, dry-run, un solo email por ejecución, pausa sin avisos, reinicio al confirmar, estados no publicados intactos, caducada fuera del público y de vuelta tras resume), `Freshness/FreshnessNotificationsTest` (contenido y enlace de los emails, tema de marca, `failed()`, validez del enlace por publicación y TTL), `Listings/ListingConfirmationPageTest` (login e `intended`, 403 a terceros, confirmación con canal y actor, reactivación de caducada y pausada, enlace caducado sin acción, vendida sin botón, acciones secundarias), `Console/ScheduleTest`, `Admin/AdminSummaryTest` y ampliación de `Admin/AdminListingsTest` (filtros y reenvío).
