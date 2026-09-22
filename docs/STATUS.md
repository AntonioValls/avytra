# AVYTRA — Project Status

## Current phase

**Phase 3 — Publicaciones: implementada, pendiente de revisión visual del propietario** (última tarea de la Definition of Done). Al aprobarla, comienza Phase 4 — Marketplace público.

Phase 2 se dio por aprobada el 2026-09-22 al pedir el propietario el inicio de Phase 3.

## Completed

### Phase 0 — Documentación y arquitectura (2026-09-21)
- Documentos `docs/00` a `docs/22`, `DECISIONS.md` (ADR-001…016), `CHANGELOG.md`, `CLAUDE.md`. Análisis de ParkingParaCamiones.

### Phase 1 — Fundamentos (2026-09-21)
- Configuración, marca, layouts (`public`, `app` con área admin, `auth`), traducción, roles y comando `avytra:superadmin`, middleware `superadmin` (404), panel `/panel`, `/admin`, tabla `audit_logs` + `AuditLogger`, rate limiters `register` y `public`. Detalle en `CHANGELOG.md`.

### Phase 2 — Dominio de empresas (2026-09-22)
- Modelo `Business` + `Location` + `OnlineProfile`, catálogo geográfico y de sectores, `BusinessPolicy`, Actions de empresa, `PublicPointDeriver`, panel y admin de empresas. Detalle en `CHANGELOG.md`.

### Phase 3 — Publicaciones (2026-09-22)
- Tablas `listings`, `listing_operation_types`, `listing_financial_metrics`, `listing_events`, `listing_slug_redirects`; modelos, factories con estados (`bare`, `draft`, `published`, `needingConfirmation`, `paused`, `expired`, `sold`, `archived`, `suspended`, `offering`, `priceRange`, `priceOnRequest`).
- Enums `ListingStatus` (tabla de transiciones en el propio enum), `OperationType`, `PriceDisclosure`, `FinancialMetric`, `ContactMethod`, `ListingEventType`.
- `ListingPolicy` (matriz completa con tests) y `BusinessPolicy::delete` restringido a empresas nunca publicadas.
- Actions: `CreateListingDraft` (invariante "una publicación abierta por empresa", copia desde una anterior para "Publicar de nuevo"), `UpdateListing`, `PublishListing`, `PauseListing`, `ResumeListing`, `ConfirmListingAvailability`, `MarkListingAsSold`, `ArchiveListing`, `SuspendListing`, `UnsuspendListing`, `ExpireListing`, `DeleteListingDraft`, `ChangeListingSlug`. Cada transición registra `listing_events`; las que ejecuta el superadmin sobre publicaciones ajenas quedan en `audit_logs`.
- `App\Support\Listings`: `ListingPublishabilityValidator` (informe con el paso del wizard que corrige cada carencia), `ListingSlugger` (slug único; evita borrados lógicos, redirecciones antiguas y segmentos de ruta), `ListingTitleSuggester`.
- Notificaciones `ListingPublished` (primera publicación) y `ListingSuspended`.
- Wizard `pages::listings.wizard` de 8 pasos con Form Objects por paso, persistencia al completar cada paso, navegación libre entre pasos una vez existe el borrador, "Guardar y salir", modo edición (`?paso=N`), título sugerido, prefill visible del contacto para propietarios, selector de propietario para el superadmin, preselección de empresa desde las tarjetas (`?empresa=ID`). Paso 7 con aviso "disponible próximamente" (decisión: no hacer trabajo desechable antes de Phase 6).
- `pages::listings.index` (tabla en escritorio, tarjetas en móvil, acciones por estado, modal de confirmación para archivar/vender/eliminar, "Publicar de nuevo").
- Panel de inicio con avisos accionables (necesita confirmación, pausada automáticamente, borrador sin terminar), lista compacta de publicaciones y tarjetas de empresas con "Nueva publicación" / "Continuar".
- Admin: `pages::admin.listings.index` (filtros por texto, estado y condición en la URL) y `pages::admin.listings.show` (resumen, timeline de eventos con `flux:timeline`, publicar, pausar, reactivar, confirmar en nombre del propietario, marcar vendida, archivar, suspender con motivo, levantar suspensión, cambiar URL con redirección).
- `DeleteUserAccount` archiva y borra las publicaciones del usuario (ADR-017).
- Componentes Blade `x-price` y `x-listing-status-badge`; parciales `partials/listing-actions` y `partials/wizard-metric`.
- Config: `avytra.limits.description_min_length_to_publish`, `title_max_length`, `highlights_max`, `highlight_max_length`.
- 303 cadenas nuevas en `lang/es.json`.

## In progress

- Nada.

## Next

- Revisión visual del propietario: wizard completo en los tres tipos de negocio (móvil y escritorio), `/panel/publicaciones` con cada estado, avisos del panel de inicio, `/admin/publicaciones` y su detalle (suspender, timeline, cambiar URL). Requiere `npm run build` (o `composer run dev`). Usuario local: `test@example.com`. Datos de prueba: `php artisan db:seed --class=DemoBusinessSeeder` (o `migrate:fresh --seed`, que ya lo incluye) crea la empresa "Tienda online de consumibles de impresoras" con su publicación publicada.
- Phase 4 — Marketplace público: `PublicListingPresenter`, home real, explorar con filtros, `x-listing-card`, `x-freshness-badge`, ficha pública con revelación de contacto, páginas de categoría/provincia, reportes, páginas estáticas. La vista previa del paso 8 del wizard debe sustituirse entonces por el parcial público real.

## Blockers

- Ninguno. Aprobaciones pendientes en su fase: `maplibre-gl` (Phase 5), `spatie/laravel-medialibrary` (Phase 6).
- Notas aceptadas de Phase 3:
  - `listings.title` y `listings.slug` son `nullable`: el borrador se crea en el paso 1 sin título; ambos son obligatorios para publicar y el slug se fija al publicar por primera vez (docs/07 y docs/15 actualizados).
  - Cuando la empresa es nueva, el borrador se crea al completar el paso 2 (la empresa necesita nombre y sector); con una empresa existente, en el paso 1. En ambos casos el resto de pasos persiste al avanzar.
  - `flux:phone` devuelve el número en formato E.164; la validación acepta `+` y dígitos con separadores y no normaliza más.
  - Los booleanos "qué se incluye" se editan con `flux:radio.group variant="segmented"` (Sí / No / Sin indicar) para conservar el tri-estado.
  - `ExpireListing` no envía notificación: la envía el comando de vigencia de Phase 7.
  - El límite `register` sigue pendiente de aplicarse (Phase 10).

## Important decisions

Ver `docs/DECISIONS.md` (ADR-001…017). Decisiones menores de Phase 3: el wizard se comparte entre panel y admin con `admin=true` como valor por defecto de ruta (igual que el formulario de empresa); las acciones de listado y admin traducen `InvalidListingTransition` a un toast en lugar de fallar; el informe de publicabilidad del paso 8 se evalúa con el título tecleado o el sugerido, para que el botón "Publicar" refleje lo que realmente se guardará.

## Last tests executed

- 2026-09-22 — `composer test` (Pint + Larastan nivel 7 + Pest): **332 tests, todo en verde** (330 de Phase 3 más `Seeders/DemoBusinessSeederTest`). Nuevos: `Unit/Enums/ListingStatusTest` (dataset de 49 transiciones), `Policies/ListingPolicyTest`, `Actions/Listings/{ListingTransitions,CreateListingDraft,ChangeListingSlug}Test`, `Support/ListingPublishabilityValidatorTest`, `Listings/{ListingWizard,ListingIndex}Test`, `Admin/AdminListingsTest`; ampliados `Policies/BusinessPolicyTest` y `Actions/Users/DeleteUserAccountTest`.

## Last updated

2026-09-22 — Phase 3 implementada y verificada con tests.
