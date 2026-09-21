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
        'confirmation_link_ttl_days' => 20,   // validez del enlace firmado del email
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

## Confirmación de un clic

Enlace firmado temporal (`URL::temporarySignedRoute('listings.confirm', now()->addDays(ttl), ['listing' => $id])`):

- **No requiere login.** Una persona mayor que recibe el email hace clic y ve "Gracias, tu publicación sigue vigente hasta el {fecha}". Riesgo: reenvío del email a un tercero permitiría renovar; el impacto es mínimo (la acción solo prolonga la vigencia). Se acepta (ADR-007).
- El enlace es válido `confirmation_link_ttl_days`; caducado → página que invita a iniciar sesión.
- La ruta es GET (los clientes de correo no hacen POST); la página muestra confirmación y un botón adicional "Marcar como vendida" que sí requiere login.
- Funciona tanto para `published` (confirm) como `expired` (resume). Para `paused` (pausa voluntaria) no reactiva: muestra "Está pausada por ti; entra al panel para reactivarla".
- Cada confirmación registra `ListingEvent::Confirmed` con `payload.channel = signed_link|dashboard|admin`.

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
- Enlace firmado válido confirma sin sesión; caducado o manipulado no.
- Enlace sobre `paused` no reactiva.
- Publicación `expired` no aparece en público y sí reaparece tras resume.
- Comando idempotente al ejecutarse dos veces seguidas.
