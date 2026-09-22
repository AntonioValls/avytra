# 06 — Ciclo de vida de una publicación

## Estados (`App\Enums\ListingStatus`)

| Estado | Valor | Visible públicamente | Quién lo provoca | Descripción |
|---|---|---|---|---|
| Borrador | `draft` | No | Usuario / superadmin | Creada, aún no publicada. Puede estar incompleta. |
| Publicada | `published` | **Sí** | Usuario / superadmin | Aparece en listados, buscador, sitemap. |
| Pausada | `paused` | No | Usuario / superadmin | Pausa voluntaria. Datos intactos. |
| Caducada | `expired` | No | Sistema (scheduler) | Pausa automática por falta de confirmación de vigencia. Datos intactos. Distinta de `paused` para poder explicar al usuario qué pasó y medirlo. |
| Vendida | `sold` | Sí, con marca "Vendida", durante un período; luego solo por URL directa | Usuario / superadmin | Operación cerrada. Estado terminal para esa publicación. La empresa sigue existiendo y puede tener una nueva publicación en el futuro. |
| Archivada | `archived` | No | Usuario / superadmin | Retirada definitiva sin venta (o tras vendida). Terminal. |
| Suspendida | `suspended` | No | Solo superadmin | Retirada por moderación. El usuario no puede editarla ni reactivarla. |

### Estados que NO se implementan en el MVP

- `pending_review` y `rejected`: implicarían moderación previa. AVYTRA usa moderación posterior (publicación inmediata + `suspended` + reportes). Documentado en [21-future-roadmap.md](21-future-roadmap.md); si se añaden en el futuro, encajan entre `draft` y `published` sin romper el resto.
- `stale` como estado: "necesita confirmación" **no es un estado**, es una condición derivada de una publicación `published` cuya `last_confirmed_at` supera el umbral del primer aviso. Se muestra como badge en el panel y no altera la visibilidad pública. Solo al superar el umbral de pausa automática cambia el estado a `expired`.

## Condiciones derivadas (no estados)

| Condición | Cálculo | Uso |
|---|---|---|
| `needsConfirmation()` | `status = published` y `now() >= last_confirmed_at + first_reminder_days` | Badge "Necesita confirmación" en panel; listado admin |
| `isPubliclyVisible()` | `status = published`, o `status = sold` y `sold_at >= now() - sold_visible_days` | Consultas públicas, sitemap |
| `daysSinceConfirmation()` | `now() - last_confirmed_at` | "Disponibilidad confirmada hace N días" |

## Máquina de estados

```text
                ┌──────────────────────────────────────────────┐
                │                                              │
 draft ───publish───▶ published ──pause──▶ paused ──resume──┘
                        │  ▲                  │
                        │  │ resume           └──archive──▶ archived
                        │  └──────── expired ◀──autoExpire──┤
                        │                │                   │
                        │                └──archive──────────┤
                        ├──markSold──▶ sold ──archive──▶ archived
                        ├──archive──────────────────────▶ archived
                        └──suspend──▶ suspended ──unsuspend──▶ published
                                          └──archive──▶ archived
 draft ──archive──▶ archived     (o eliminación física del borrador)
 paused/expired ──markSold──▶ sold
 paused/expired ──suspend──▶ suspended
```

### Transiciones permitidas

| Desde | Acción | Hacia | Actor | Efectos |
|---|---|---|---|---|
| `draft` | `publish` | `published` | Propietario, superadmin | Valida "listo para publicar". `published_at = now()` (solo la primera vez), `last_confirmed_at = now()`, `next_confirmation_at = now() + period`. Evento `published`. |
| `draft` | `archive` | `archived` | Propietario, superadmin | |
| `draft` | `delete` | — | Propietario, superadmin | Eliminación física permitida **solo** en borradores. |
| `published` | `pause` | `paused` | Propietario, superadmin | `paused_at = now()`. Evento `paused`. |
| `published` | `autoExpire` | `expired` | Sistema | `expired_at = now()`. Evento `expired`. Notificación. |
| `published` | `confirm` | `published` | Propietario (con sesión iniciada, desde el panel o desde la página a la que enlaza el email), superadmin en su nombre | Actualiza `last_confirmed_at`, `next_confirmation_at`, resetea `*_reminder_sent_at`. Evento `confirmed`. |
| `published` | `markSold` | `sold` | Propietario, superadmin | `sold_at = now()`. Evento `sold`. |
| `published` | `archive` | `archived` | Propietario, superadmin | |
| `published` | `suspend` | `suspended` | Superadmin | `suspended_at`, `suspension_reason`. Notificación al propietario. Audit log. |
| `paused` | `resume` | `published` | Propietario, superadmin | Equivale a confirmar: actualiza `last_confirmed_at` y `next_confirmation_at`. Evento `resumed`. |
| `paused` | `markSold` | `sold` | Propietario, superadmin | |
| `paused` | `archive` | `archived` | Propietario, superadmin | |
| `paused` | `suspend` | `suspended` | Superadmin | |
| `expired` | `resume` (confirmar) | `published` | Propietario, superadmin | Igual que desde `paused`. El enlace de confirmación del email funciona también aquí. |
| `expired` | `markSold` | `sold` | Propietario, superadmin | |
| `expired` | `archive` | `archived` | Propietario, superadmin | |
| `expired` | `suspend` | `suspended` | Superadmin | |
| `sold` | `archive` | `archived` | Propietario, superadmin, sistema (tras `sold_visible_days`, opcional) | |
| `suspended` | `unsuspend` | `published` | Superadmin | Reinicia vigencia como una publicación recién publicada. |
| `suspended` | `archive` | `archived` | Superadmin | |
| `archived` | — | — | — | Terminal. Para volver a vender, se crea una nueva publicación de la misma empresa. |

Cualquier otra transición lanza `App\Exceptions\InvalidListingTransition`.

### Reglas adicionales

- `published_at` se fija solo la primera vez que se publica; los `resume` no lo alteran (SEO y orden "recientes" estables). Para orden por actividad se usa `last_confirmed_at`.
- Una publicación `sold` no vuelve a `published`. Si el trato se cae, se crea una nueva publicación (la empresa sigue en el sistema con todos sus datos; el wizard permite "duplicar desde la última publicación").
- Editar una publicación `published` no cambia su estado ni exige re-publicar. La edición de campos sensibles por parte del superadmin queda en `audit_logs`.
- Una publicación `suspended` no es editable por el propietario; puede contactar con AVYTRA.
- Al archivar o vender, la empresa queda libre para una nueva publicación (invariante "una publicación no terminada por empresa").

## Implementación prevista

- Enum `ListingStatus` con métodos `label()`, `color()`, `isTerminal()`, `isPubliclyVisible()`, `canTransitionTo(ListingStatus $to): bool` (tabla de transiciones en el propio enum).
- Un Action por transición en `App\Actions\Listings\` (`PublishListing`, `PauseListing`, `ResumeListing`, `ConfirmListingAvailability`, `MarkListingAsSold`, `ArchiveListing`, `SuspendListing`, `UnsuspendListing`, `ExpireListing`). Cada Action: comprueba la transición, actualiza timestamps dentro de una transacción, registra `ListingEvent`, dispara notificación si procede. **No** comprueban permisos (eso es de las Policies, que se invocan antes desde el componente Livewire o comando).
- Todos los timestamps y estados se calculan con `now()` en la zona `Europe/Madrid`.
- Los umbrales viven en `config/avytra.php` (ver [13-freshness-and-notifications.md](13-freshness-and-notifications.md)).

Implementado en Phase 3: además de los Actions de transición existen `CreateListingDraft` (aplica la invariante de una publicación abierta por empresa y permite copiar una anterior), `UpdateListing` (contenido, tipos de operación y métricas; nunca el estado), `DeleteListingDraft` y `ChangeListingSlug`. El trait `App\Actions\Listings\Concerns\RecordsListingEvents` centraliza la comprobación de la tabla de transiciones, el registro en `listing_events` (con `on_behalf_of_user_id` cuando actúa el superadmin) y la auditoría. `ResumeListing` y `ConfirmListingAvailability` reciben el canal (`dashboard`, `email_link`, `admin`) y lo guardan en el payload del evento.

## Vista del usuario (badges en panel)

| Estado / condición | Badge | Color Flux |
|---|---|---|
| `draft` | Borrador | zinc |
| `published` sin `needsConfirmation` | Publicada | lime |
| `published` con `needsConfirmation` | Necesita confirmación | amber |
| `paused` | Pausada | zinc |
| `expired` | Pausada por falta de confirmación | amber/red |
| `sold` | Vendida | blue |
| `archived` | Archivada | zinc |
| `suspended` | Suspendida | red |

Acciones mostradas por estado (ver [09-dashboard-and-admin.md](09-dashboard-and-admin.md)).
