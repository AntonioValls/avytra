# AVYTRA — Changelog

Formato: una sección por fase cerrada, con fecha. Cambios de documentación relevantes también se anotan.

## [Phase 4] — 2026-09-22 — Marketplace público

### Añadido
- `App\Support\Listings\PublicListingPresenter` (proyección pública única: ubicación según `location_visibility` efectiva, precio y métricas según divulgación, web y redes según `website_visibility`, forma jurídica según `show_legal_form`, canales de contacto públicos/sensibles, texto de vigencia, `pageMeta()` con JSON-LD `Offer` y `BreadcrumbList`, `withTitle()` para la vista previa), `PriceFormatter`, `MarketplaceAggregates` (recuentos por sector/provincia y provincias cacheados como arrays).
- Rutas públicas: `/` (`HomeController`), `/empresas`, `/empresas/categoria/{slug}`, `/empresas/provincia/{slug}`, `/negocios-online` (`pages::public.listings.index`, filtros `#[Url]`), `/empresas/{slug}` (`ListingController@show`), `/publicar`, `/como-funciona`, `/aviso-legal`, `/privacidad`, `/cookies`; todas bajo `throttle:public`.
- Componentes Livewire `public.contact-box` (revelación de teléfono/WhatsApp/email con limitador `contact-reveal`) y `public.report-listing` (modal con honeypot, tiempo mínimo, limitador `report` y un reporte abierto por visitante).
- Reportes: migración `listing_reports`, modelo `ListingReport` (+factory), enums `ListingReportReason` y `ListingReportStatus`, `ListingReportPolicy`, Actions `Reports\SubmitListingReport` y `Reports\ResolveListingReport` (audit `listing_report.resolved|dismissed`), notificación `ListingReportReceived` a superadmins, bandeja `pages::admin.reports.index` (`/admin/reportes`) con acción rápida pausar/suspender/archivar; entrada "Reportes" en el sidebar admin.
- Componentes Blade `x-listing-card`, `x-freshness-badge`, `x-public.legal-page`; vistas `public/home` (real), `public/listings/show` + `partials/content` + `partials/filters`, `public/publish`, `public/how-it-works`, `public/legal/*`, `errors/404` y `errors/410` con layout público.
- Relaciones `Listing::reports()`, `Category::businesses()`, `Province::locations()`.
- Config `avytra.pagination.public_cards` (24), `avytra.public.{home_latest_listings, related_listings, aggregates_cache_minutes, price_filter_max}`, `avytra.reports.{min_seconds_to_submit, message_max_length}`; limitadores `contact-reveal` y `report` en `AppServiceProvider`.
- Tests: `Support/PublicListingPresenterTest` (visibilidad por dataset, divulgación, JSON-LD sin coordenadas privadas), `Public/ListingShowTest` (never leaks, revelación y rate limit, 404/410/301, propietario con banner, vendida), `Public/ListingExploreTest` (cada filtro, orden, paginación, vacío, categoría/provincia/online, canonical), `Public/StaticPagesTest`, `Reports/ListingReportTest`, `Policies/ListingReportPolicyTest`.
- 201 cadenas nuevas en `lang/es.json`.

### Cambiado
- Rutas del panel de publicaciones renombradas a `panel.listings.index|create|edit` (el nombre `listings.index` pasa a explorar, docs/08).
- `x-price` es presentacional (`text`, `negotiable`); el admin y el wizard formatean con `PriceFormatter`.
- La vista previa del paso 8 del wizard renderiza el parcial público real mediante el presentador.
- Header público con navegación (Explorar, Negocios online, Vende tu empresa) y footer con sectores, provincias con publicaciones, legales y soporte.
- Documentación: docs 08, 09, 12, 15 y 16 con notas de implementación; STATUS.

## [Phase 3] — 2026-09-22 — Publicaciones

### Añadido
- Tablas `listings` (título y slug nullables hasta publicar; índices `(status, published_at)`, `(status, next_confirmation_at)`, `(status, last_confirmed_at)`), `listing_operation_types`, `listing_financial_metrics`, `listing_events`, `listing_slug_redirects`; modelos `Listing` (soft deletes, `TracksAuthorship`, scopes `ownedBy`, `open`, `publiclyVisible`, `needingConfirmation`, `dueForExpiration`; `needsConfirmation()`, `isPubliclyVisible()`, `daysSinceConfirmation()`), `ListingOperationType`, `ListingFinancialMetric`, `ListingEvent`, `ListingSlugRedirect`; factories `ListingFactory` (estados por ciclo de vida, `offering`, `bare`, `priceRange`, `priceOnRequest`), `ListingFinancialMetricFactory`, `ListingEventFactory`.
- Enums `ListingStatus` (`allowedTransitions()`, `canTransitionTo()`, `isTerminal()`, `isPubliclyVisible()`, `isEditableByOwner()`, `badgeColor()`), `OperationType` (`allowsStake()`, `titlePrefix()`), `PriceDisclosure`, `FinancialMetric` (`isFeatured()`, `appliesTo()`), `ContactMethod` (`channelColumn()`, `actionLabel()`), `ListingEventType`.
- Excepciones `InvalidListingTransition`, `BusinessAlreadyListed`, `ListingNotPublishable` (con `userMessage()`).
- `ListingPolicy` con `before()` para superadmin; `BusinessPolicy::delete` exige que ninguna publicación haya sido publicada; `Business::listings()`, `Business::openListing()`, `Business::hasPublishedListings()`, `User::listings()`.
- Actions `App\Actions\Listings\*`: `CreateListingDraft`, `UpdateListing`, `PublishListing`, `PauseListing`, `ResumeListing`, `ConfirmListingAvailability`, `MarkListingAsSold`, `ArchiveListing`, `SuspendListing`, `UnsuspendListing`, `ExpireListing`, `DeleteListingDraft`, `ChangeListingSlug`, con el trait `Concerns\RecordsListingEvents` (transición guardada por la tabla del enum, evento en `listing_events`, audit cuando el actor no es el propietario, reinicio de vigencia).
- `App\Support\Listings\{ListingPublishabilityValidator, PublishabilityReport, PublishabilityIssue, ListingSlugger, ListingTitleSuggester}`.
- Notificaciones en cola `ListingPublished` y `ListingSuspended`.
- Form Objects `App\Livewire\Forms\ListingWizard\{OperationStepForm, CharacteristicsStepForm, EconomicsStepForm, ContactStepForm, PublishStepForm}`.
- Páginas Livewire `pages::listings.wizard` (8 pasos; compartida con admin), `pages::listings.index`, `pages::admin.listings.index`, `pages::admin.listings.show`; `pages::dashboard` con avisos accionables y lista de publicaciones.
- Rutas `/panel/publicaciones`, `/panel/publicaciones/nueva` (`?empresa=ID` preselecciona), `/panel/publicaciones/{listing}/editar` (`?paso=N`), `/admin/publicaciones`, `/admin/publicaciones/nueva`, `/admin/publicaciones/{listing}`, `/admin/publicaciones/{listing}/editar`.
- Componentes Blade `x-price`, `x-listing-status-badge`; parciales `partials/listing-actions`, `partials/wizard-metric`. Entradas "Mis publicaciones" y "Publicaciones" en el sidebar.
- Config `avytra.limits.{description_min_length_to_publish, title_max_length, highlights_max, highlight_max_length}`.
- Tests: `ListingStatusTest` (unit, dataset completo de transiciones), `ListingPolicyTest`, `ListingTransitionsTest`, `CreateListingDraftTest`, `ChangeListingSlugTest`, `ListingPublishabilityValidatorTest`, `ListingWizardTest`, `ListingIndexTest`, `AdminListingsTest`.
- 303 cadenas nuevas en `lang/es.json`.
- `DemoBusinessSeeder` (llamado desde `DatabaseSeeder`): empresa híbrida de prueba "Tienda online de consumibles de impresoras" en Borriana, con ubicación, perfil online y publicación publicada, propiedad de `test@example.com`. Idempotente; pasa por los mismos Actions que el wizard. Test `Seeders/DemoBusinessSeederTest`.

### Cambiado
- `DeleteUserAccount` archiva (evento `archived`) y borra las publicaciones antes de borrar cada empresa.
- Tarjetas de "Mis empresas" y del panel muestran el estado de la publicación abierta y las acciones "Nueva publicación" / "Continuar" / "Ver publicación".
- Documentación: docs 04 (evento `created`), 06 (implementación), 07 (`title`/`slug` nullables, índice adicional), 09 (implementación del wizard y de admin), STATUS.

## [Phase 2] — 2026-09-22 — Dominio de empresas

### Añadido
- Tablas `categories`, `regions`, `provinces`, `municipalities`, `businesses`, `locations`, `online_profiles` con sus modelos, factories (estados `physical/online/hybrid`, `withLocation`, `withOnlineProfile`, `ownedBy`, `createdBy`; `Location` con `exact/approximate/cityOnly/hidden/withCoordinates`) y PHPDoc para Larastan.
- Enums `BusinessType`, `LegalForm`, `EmployeeRange`, `LocationVisibility`, `WebsiteVisibility`, `GeocodingSource`, `OnlineBusinessType`, `TechnologyPlatform`, `LogisticsType`, `AcquisitionChannel`, `Disclosure`, todos con `label()`.
- Catálogo geográfico: `database/data/spain/{regions,provinces,municipalities}.csv` (19/52/8.131, con centroides y población; fuentes en `database/data/spain/README.md`) y comando idempotente `avytra:import-geography`.
- `CategorySeeder` con 16 sectores y 80 subsectores reales; `DatabaseSeeder` siembra categorías y geografía.
- Trait `App\Concerns\TracksAuthorship` (aplicado a `Business`).
- `BusinessPolicy` con `before()` para superadmin y habilidad `transferOwnership`.
- Actions `CreateBusiness`, `UpdateBusiness` (mantiene coherencia tipo ↔ ubicación/perfil online), `TransferBusinessOwnership`, `SaveBusinessLocation` (único punto que escribe `public_*`), `DeleteUserAccount` (ADR-017).
- `App\Support\Location\{PublicPointDeriver, PublicPoint, Coordinates}`: derivación de coordenadas públicas por visibilidad con desplazamiento determinista y radios por población. Registrado en el contenedor desde `config/avytra.php`.
- Form Objects `BusinessForm`, `LocationForm`, `OnlineProfileForm`.
- Páginas Livewire `pages::dashboard`, `pages::businesses.index`, `pages::businesses.form` (compartida con admin) y `pages::admin.businesses.index` (tabla, filtros por texto/tipo/sector en URL, cambio de propietario con modal y audit).
- Rutas `/panel/empresas`, `/panel/empresas/crear`, `/panel/empresas/{business}/editar`, `/admin/empresas`, `/admin/empresas/crear`, `/admin/empresas/{business}/editar`.
- Componente Blade `x-empty-state`. Entradas "Mis empresas" y "Empresas" en el sidebar.
- Config: `avytra.location.approximate_offset_{min,max}_m`, `avytra.location.city_only_radius_m`, `avytra.pagination.*`, `avytra.limits.description_max_length`.
- Helpers de test `actingAsSuperadmin()` y `actingAsOwnerOf()`.
- Tests: `PublicPointDeriverTest` (unit), `BusinessPolicyTest`, `TracksAuthorshipTest`, `CreateBusinessTest`, `UpdateBusinessTest`, `TransferBusinessOwnershipTest`, `SaveBusinessLocationTest`, `DeleteUserAccountTest`, `ImportSpanishGeographyTest`, `CategorySeederTest`, `BusinessFormTest`, `BusinessIndexTest`, `AdminBusinessesTest`; invariante de config de ubicación.
- 199 cadenas nuevas en `lang/es.json`.

### Cambiado
- `/panel` pasa de vista estática a componente Livewire `pages::dashboard` (muestra las empresas del usuario o el estado vacío con enlace a crear).
- `layouts/app` deduce `area` del nombre de la ruta cuando no se indica.
- Eliminar cuenta (`settings/⚡delete-user-modal`) usa `DeleteUserAccount`.
- `User::businesses()`.
- Documentación: ADR-017; docs 03, 04, 07, 09, 10 y 16 actualizados.

## [Phase 1] — 2026-09-21 — Fundamentos del proyecto

### Añadido
- `config/avytra.php` (vigencia, contacto, reportes, ubicación, mapa, geocodificación, soporte, límites) y variables `AVYTRA_*` en `.env.example`.
- Enum `UserRole`; migración `users.role`, `users.phone`, `users.is_assisted`; `User::isSuperadmin()`; estados de factory `superadmin()` y `assisted()`.
- Comando `avytra:superadmin {email} [--revoke]`.
- Middleware `EnsureUserIsSuperadmin` (alias `superadmin`, responde 404) y `routes/admin.php` con `/admin`.
- Tabla `audit_logs`, modelo `AuditLog`, factory y `App\Support\Audit\AuditLogger`.
- `App\Support\Seo\PageMeta` y layout público `layouts/public` con header, footer y metadatos.
- Componentes `x-app-logo` (wordmark) y `x-app-logo-icon` (símbolo) con los SVG de marca; favicon, apple-touch-icon, app icon y `og-default.png`.
- Traducciones `lang/es.json` y `lang/es/*.php`.
- Rate limiters `register` y `public`.
- Tests: acceso admin, comando superadmin, audit logger, configuración, home pública.
- README de arranque y `.claude/launch.json` para el servidor de previsualización.

### Cambiado
- Locale `es`, zona horaria `Europe/Madrid`, Fortify `home` → `/panel`; `/dashboard` pasa a `/panel` manteniendo el nombre `dashboard`.
- `app.css`: tokens de marca, Lato, accent de Flux en Lime/Ink. Vite carga Lato autoalojada en lugar de Instrument Sans.
- Layouts `app` (prop `area`, sidebar Ink en administración, sin enlaces del starter kit), `auth` (claros, logo real) y `partials/head` (modo claro por defecto).
- Página de apariencia sin opción "Sistema".
- `SecurityTest` usa `__()` para las cadenas.
- `composer types:check` con `--memory-limit=1G`; conexión mysql con `engine = InnoDB`.
- `Model::preventLazyLoading` activo fuera de producción.

### Eliminado
- `welcome.blade.php`, `placeholder-pattern.blade.php`, enlaces a repositorio/documentación del starter kit, `lang/en` publicado.

## [Phase 0] — 2026-09-21 — Documentación y arquitectura

### Añadido
- Documentación modular en `docs/` (00–22): visión, alcance, roles, dominio, campos, ciclo de vida, base de datos, páginas públicas, panel/admin, ubicación y mapas, negocios online, contacto, vigencia, diseño, SEO, seguridad, medios, tests, arquitectura, fases, roadmap, referencia ParkingParaCamiones.
- `docs/DECISIONS.md` con ADR-001 a ADR-014.
- `docs/STATUS.md` y `docs/CHANGELOG.md`.
- `CLAUDE.md` con las instrucciones permanentes del proyecto (sección AVYTRA antes del bloque de Laravel Boost).

### Cambiado
- `.gitignore`: se retira `/CLAUDE.md` para versionar las instrucciones del proyecto.
- Tras la revisión del propietario: varios tipos de operación por publicación (`listing_operation_types`, `primary_operation_type`, `stake_percent`, `operation_notes`, nuevo valor `partial_sale`) — ADR-015; propiedad directa por usuario sin equipos — ADR-016; confirmación de vigencia con login en lugar de enlace sin sesión — ADR-007 revisado. Documentos 04, 05, 06, 07, 08, 09, 13, 16, 18, 19, 20 y 21 actualizados.

### Sin cambios de código
- No hay migraciones, modelos, componentes ni dependencias nuevas.
