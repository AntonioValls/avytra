# AVYTRA — Changelog

Formato: una sección por fase cerrada, con fecha. Cambios de documentación relevantes también se anotan.

## [Phase 11] — 2026-09-26 — Formulario de contacto relay

### Añadido
- Tabla `contact_requests`, modelo `ContactRequest` (`#[Fillable]` solo en los campos del remitente; scopes `receivedBy`, `unread`, `undelivered`), factory con estados `forListing`, `from`, `read`, `undelivered`, y `ContactRequestPolicy` (`viewAny` para cualquier usuario, `view` y `markAsRead` para el propietario de la publicación, `before()` superadmin). ADR-019.
- Actions `App\Actions\Contact\SubmitContactRequest` (guarda y envía; `sentToday()` para el tope diario) y `MarkContactRequestAsRead`. `Listing::contactRequests()`, `contactInboxEmail()` (`contact_email` o el email de la cuenta del propietario) y `contactInboxName()`.
- Notificación en cola `ContactRequestReceived`, bajo demanda al buzón resuelto, con `Reply-To` del interesado y botón "Ver mis mensajes"; `failed()` marca `delivery_failed_at` y `delivery_error`. No se envía copia al remitente.
- Ficha pública: "Enviar mensaje" sustituye a "Mostrar email" (`public.contact-request-form`, modal `contact-request` anidado en `public.contact-box`), presente en toda publicación `published` aunque no tenga `contact_email`. Honeypot, tiempo mínimo, limitador nombrado `contact-request` (por IP y hora) y tope diario por publicación e IP; con sesión, nombre y email prerrellenados.
- `PublicListingPresenter::isRelayChannel()`; `isSensitiveChannel()` queda para teléfono y WhatsApp; `contactMethods()` incluye siempre el email; `publicChannel()` y `sensitiveChannel()` nunca lo devuelven.
- Panel: página `/panel/mensajes` (`pages::messages.index`, ruta `panel.messages.index`) con no leídos primero, detalle con "Responder por email" (`mailto:` con asunto "Re: …") y aviso de email no entregado; entrada "Mensajes" con contador en la barra lateral (`User::unreadContactRequestsCount()`) y aviso "Tienes N mensajes sin leer" en el inicio del panel.
- Admin: tarjeta "Mensajes recibidos" (solo lectura) en el detalle de la publicación y contador "Mensajes no entregados" en el resumen operativo.
- `App\Support\Security\IpHash` (compartido por reportes y relay). `config/avytra.php` → `contact.request_rate_limit_per_hour`, `requests_per_listing_per_day`, `request_message_max_length`, `request_min_seconds_to_submit`.
- Tests `Contact/SubmitContactRequestTest`, `Contact/ContactRequestFormTest`, `Contact/MessagesPageTest`, `Contact/AdminContactRequestsTest`, `Policies/ContactRequestPolicyTest`; ajustes en `Public/ListingShowTest` y `Support/PublicListingPresenterTest`. 44 cadenas nuevas en `lang/es.json`.

### Cambiado
- Textos del paso 6 del wizard: el email de contacto nunca se muestra; es el buzón de los mensajes.
- Documentación: `CLAUDE.md`, docs 03, 07, 08, 09, 12, 16, 18, 19, 20 (sección Phase 11), 21, `DECISIONS.md` (ADR-019).

## [Phase 10] — 2026-09-26 — Endurecimiento y lanzamiento

### Añadido
- Middleware `AddSecurityHeaders` (grupo `web`): `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` y `Strict-Transport-Security` sobre HTTPS en producción. Sin CSP estricta (ADR-018).
- Middleware `ThrottleRegistration` (grupo `web`): limitador nombrado `register` (5/hora por IP) sobre `POST /register`, compatible con `route:cache`. Honeypot `website` y `form_opened_at` en el registro, comprobados en `CreateNewUser::rejectBots()` (`avytra.registration.min_seconds_to_submit`). `email:rfc,dns` solo en producción.
- Latido del scheduler: `App\Support\Monitoring\SchedulerHeartbeat`, tarea `avytra:scheduler-heartbeat` cada minuto y aviso rojo en el resumen operativo cuando pasa de `avytra.monitoring.scheduler_stale_minutes`. Aviso de 2FA para el superadmin sin segundo factor, con enlace a los ajustes de seguridad.
- Página de mantenimiento de marca `resources/views/errors/503.blade.php`, autónoma (sin Vite, Livewire ni base de datos), para `php artisan down --render="errors::503"`.
- Blaze: compilación opt-in de `listing-card`, `price`, `freshness-badge`, `listing-status-badge` y `empty-state` (`AppServiceProvider::configureBlaze()`).
- CI: paso "Audit dependencies" (`composer audit`, `npm audit --audit-level=high`) en `.github/workflows/tests.yml`.
- `docs/23-deployment.md`: requisitos, variables de entorno, primer despliegue y posteriores, worker y cron, copias de seguridad, logs, smoke test y pendientes del propietario. ADR-018.
- Tests `Security/HardeningTest` (cabeceras, honeypot y tiempo mínimo, limitador de registro, página 503, latido y avisos del resumen). 9 cadenas nuevas en `lang/es.json`.

### Cambiado
- Documentación: docs 00, 16, 19, `CLAUDE.md` (mapa de docs), STATUS con el checklist de lanzamiento.

## [Phase 9] — 2026-09-26 — SEO

### Añadido
- `App\Support\Seo\Sitemap` y `SitemapController` (`/sitemap.xml`): home, explorar, estáticas, online (si hay), sectores y provincias con contenido y fichas visibles con `lastmod`; caché de `avytra.seo.sitemap_cache_minutes` olvidada (con los agregados) en cada transición de estado y cambio de URL (`RecordsListingEvents::forgetPublicCaches()`, `DB::afterCommit`).
- `RobotsController` (`/robots.txt`): `Disallow` desde `avytra.seo.robots_disallow` y `Sitemap:` con `app.url`. Se elimina `public/robots.txt`.
- Middleware `RedirectExploreFiltersToLandingPages` (`explore-redirects`): `/empresas?sector=` y `/empresas?provincia=` redirigen 301 a su página propia conservando los demás filtros.
- JSON-LD `ItemList` + `BreadcrumbList` en categoría, provincia y online con resultados; `Organization` en la home. `og:image:width/height` y etiquetas `twitter:*`; descripción propia en las páginas legales; descripción de provincia con recuento y sectores; `noindex, nofollow` en panel, admin y auth.
- `CategorySeeder::DESCRIPTIONS`: introducción real de cada sector.
- Tests `Public/SitemapTest` y `Public/SeoTest`; 4 cadenas nuevas en `lang/es.json`.

### Cambiado
- `ListingExploreTest`: el test del canonical de `?sector=` pasa a comprobar el 301.
- Documentación: docs 15 con notas de implementación, STATUS.

## [Phase 8] — 2026-09-26 — Administración y asistencia

### Añadido
- `App\Policies\UserPolicy`: `viewAny`, `create` y `sendPasswordLink` solo superadmin; `view` y `update` sobre la propia cuenta; `changeRole` siempre denegado, también al superadmin (no pasa por `before()`).
- Actions `App\Actions\Users\CreateAssistedUser` (cuenta verificada por el admin, contraseña aleatoria, `is_assisted`, alias `local+slug@dominio` del buzón de soporte cuando la persona no tiene email, audit `user.created_by_admin`), `UpdateUserByAdmin` (nombre, email y teléfono; audit `user.updated_by_admin` solo con los campos cambiados) y `SendSetPasswordLink` (token del broker de Fortify, audit `user.password_link_sent_by_admin`). Notificación `SetPasswordInvitation` ("Tu cuenta en AVYTRA está lista"). Form Object `AdminUserForm`.
- Admin: `/admin/usuarios` (`pages::admin.users.index`: búsqueda por nombre/email/teléfono, filtro de cuentas asistidas y superadmins, modal de alta), `/admin/usuarios/{user}` (`pages::admin.users.show`: datos editables, empresas y publicaciones, rastro de auditoría, reenvío del enlace de contraseña, botones "Nueva empresa/publicación para este usuario" con `?propietario=ID` preseleccionando el propietario en el formulario de empresa y en el wizard) y `/admin/auditoria` (`pages::admin.audit.index`: filtros por actor, acción, tipo de recurso y usuario afectado; cambios desplegables). Catálogo de acciones con etiquetas en `App\Support\Audit\AuditActions`.
- Búsqueda global `admin.command-palette` (`flux:command` en un modal, Ctrl/Cmd+K) sobre usuarios, empresas y publicaciones desde la barra lateral admin. Resumen operativo con la tarjeta "Cuentas asistidas". Enlaces "Usuarios" y "Auditoría" en la navegación admin.
- Tests: `Policies/UserPolicyTest`, `Admin/AdminUsersTest`, `Admin/AdminAuditTest`, `Admin/AssistedFlowTest` (flujo "llamada de teléfono" completo: cuenta → empresa → publicación → publicar → confirmar en su nombre, con `created_by`, propietario y audit comprobados; la persona ve todo como suyo y el superadmin no). 79 cadenas nuevas en `lang/es.json`.

### Corregido
- Wizard: al elegir una empresa existente en el paso 1 (o llegar con `?empresa=`) ahora se rellenan también los formularios de ubicación y perfil online, de modo que el paso 5 muestra lo ya guardado en lugar de campos vacíos que lo sobrescribirían.

### Cambiado
- Documentación: docs 03, 09 y 16 con notas de implementación, STATUS.

## [Phase 7] — 2026-09-26 — Sistema de vigencia

### Añadido
- Comando `avytra:listings:process-freshness {--dry-run}` (`App\Console\Commands\ProcessListingFreshness`): tres pasadas idempotentes (primer aviso, segundo aviso, pausa automática) sobre los scopes `dueForReminder(ReminderStage)` y `dueForExpiration()` con `chunkById(200)`; como máximo un email por publicación y ejecución. Programado cada hora con `withoutOverlapping()` en `routes/console.php`.
- Enum `App\Enums\ReminderStage` (`first`, `second`); `ListingEventType::ReminderFailed`.
- Actions `SendListingFreshnessReminder` (flag `*_reminder_sent_at` y evento `reminder_sent` antes de encolar) y `ResendFailedReminder` (reenvío manual por el superadmin con audit `listing.reminder_resent_by_admin`); `ExpireListing` envía ahora `ListingExpired`.
- Notificaciones `ListingFreshnessReminder` (dos etapas) y `ListingExpired` en cola con `tries` y `backoff`; `failed()` registra `reminder_failed` en el historial. Tema de correo de marca `avytra` (`config/mail.php` → `markdown`, `resources/views/vendor/mail/html/themes/avytra.css`).
- `App\Support\Listings\ConfirmationLink` (`for()` con firma temporal de `confirmation_link_ttl_days`, `isValid()` sobre la URL canónica de la ruta).
- Página autenticada `pages::listings.confirm` en `/panel/publicaciones/{listing}/confirmar` (ADR-007): un botón "Sí, sigue disponible" (`confirm` o `resume` con canal `email_link`), "Reactivar" para pausadas, secundarios "Marcar como vendida" y "Pausar", explicación con enlace caducado. Limitador nombrado `confirmation` (`avytra.rate_limits.confirmation_per_hour`, 30/hora por usuario).
- Panel: botón "Sigue disponible" visible en fila y tarjeta cuando la publicación necesita confirmación.
- Admin: resumen operativo real `pages::admin.index` (necesitan confirmación, pausadas automáticamente en `avytra.freshness.expired_review_days`, avisos no entregados, reportes abiertos, últimas publicaciones y usuarios); filtros `expired_recently` y `failed_reminder` en `/admin/publicaciones`; callout y botón "Reenviar" en el detalle. Scopes `expiredRecently()`, `withFailedReminder()` y `Listing::latestFailedReminder()`.
- Tests: `Freshness/ProcessListingFreshnessTest`, `Freshness/FreshnessNotificationsTest`, `Listings/ListingConfirmationPageTest`, `Console/ScheduleTest`, `Admin/AdminSummaryTest`; ampliado `Admin/AdminListingsTest`. 45 cadenas nuevas en `lang/es.json`.

### Cambiado
- `Route::view('admin.index')` pasa a `Route::livewire('pages::admin.index')`; se elimina la vista placeholder `resources/views/admin/index.blade.php`.
- Documentación: docs 06, 09, 13 y 16 con notas de implementación, STATUS.

## [Phase 6] — 2026-09-23 — Medios

### Añadido
- Dependencia `spatie/laravel-medialibrary` ^11.23 (ADR-006, aprobada por el propietario al iniciar la fase; trae `spatie/image` 3, driver GD). Migración `create_media_table` y `config/media-library.php` publicada: originales en `MEDIA_ORIGINALS_DISK` (`local`, privado), conversiones en `MEDIA_DISK` (`public`), `App\Support\Media\BusinessPathGenerator` (`businesses/{business_id}/{media_id}/`), conversiones en cola (`MEDIA_QUEUE_CONVERSIONS`) tras el commit. `.env.example` con las tres variables.
- Config `avytra.media` (`originals_disk`, `disk`, `gallery_max` 12, `max_kilobytes` 8192, dimensiones mínimas/máximas, `logo_min_*`, `allowed_extensions`, `upload_rate_limit_per_hour` 30, `quality` 82 y las conversiones `thumb` 400×250, `card` 800×500, `detail` máx. 1600, `og` 1200×630, `logo` máx. 256).
- `Business implements HasMedia` con colecciones `logo`/`cover` (un archivo) y `gallery` desde el enum `App\Enums\MediaCollection`, conversiones WebP por colección y helpers `cover()`, `logo()`, `galleryImages()`. El borrado suave conserva los medios; el borrado definitivo (cuenta) los elimina en cascada.
- Actions `App\Actions\Media\{AddBusinessImage, RemoveBusinessImage, ReorderBusinessGallery, UpdateBusinessImageAlt}` y excepción `GalleryFull`: nombre de archivo aleatorio, alt por defecto, rechazo (404) de medios ajenos, audit `business.images_updated_by_admin` cuando actúa el superadmin.
- Componente Livewire `businesses.images` (`resources/views/components/businesses/⚡images.blade.php`): portada, galería y logo con `flux:file-upload` (progreso), subida inmediata, validación estricta, `wire:sort` para el orden, alt editable en línea, `wire:confirm` al quitar, limitador nombrado `image-upload` por usuario y `wire:poll` mientras haya conversiones pendientes. Incrustado en el paso 7 del wizard y en el formulario de empresa (al editar; al crear, aviso).
- Público: `App\Support\Media\PublicImage` y `PublicListingPresenter::{coverImage, galleryImages, logoImage, ogImageUrl}` (solo URLs de conversión; `null` hasta que la conversión existe); `RELATIONS` incluye `business.media`. `x-listing-card` con portada en `srcset` `thumb`/`card`; ficha con portada `detail` (`fetchpriority="high"`), miniaturas, lightbox Alpine con flechas y teclado, logo en "Sobre la empresa"; `og:image` con la conversión `og` y `Offer.image`. Vista previa del paso 8 con la portada; tarjetas de "Mis empresas" con la portada.
- Tests: `Media/BusinessImagesTest` (subida, reemplazo, rechazos por tipo/tamaño/dimensiones, máximo de galería, alt, orden, borrado de archivos, 403 a terceros, audit del superadmin, limitador, render en wizard y formulario), `Actions/Media/BusinessImageActionsTest` (galería llena, reorden con ids ajenos, medios ajenos, audit del alt, borrado de cuenta) y `Public/ListingImagesTest` (solo conversiones en HTML, `srcset`, placeholder, portada suplente, conversiones en cola). `phpunit.xml` sube `memory_limit` a 1G (conversiones GD en toda la suite).
- 31 cadenas nuevas en `lang/es.json` (se retiran las tres del aviso "las imágenes llegan pronto").

### Cambiado
- El paso 7 del wizard y la página `/publicar` dejan de anunciar "próximamente".
- Documentación: docs 07, 08, 09, 15, 16, 17 y 19 con notas de implementación, ADR-006 aceptado con resultado, `CLAUDE.md` (dependencias aprobadas), STATUS.

## [Phase 5] — 2026-09-23 — Ubicación y mapas

### Añadido
- Dependencia npm `maplibre-gl` ^6.11 (ADR-005, aprobada por el propietario al iniciar la fase). Entrada Vite `resources/js/map.js` (incluida solo por los componentes de mapa) y módulo `resources/js/map/` (`support.js` con comprobación WebGL2 y fallback, `tokens.js` con Ink y Transfer Blue desde las variables CSS del tema, `geometry.js`, `layers.js`, `listing.js`, `explore.js`, `picker.js`). El worker de MapLibre v6 se importa con `?worker&url` y `setWorkerUrl()`: Vite lo empaqueta en el build y lo sirve en desarrollo, sin copias manuales.
- Componentes Blade `x-map.listing` (ficha y vista previa del wizard; pin para `exact`, círculo para `approximate`/`city_only`, nada para `hidden`; fallback con enlace a OpenStreetMap sobre coordenadas públicas), `x-map.explore` (mapa opcional de explorar con los puntos públicos de la página actual, popups construidos con nodos DOM) y `x-map.picker` (mapa con pin arrastrable o por clic, centrado en el municipio elegido, coordenadas privadas en inputs ocultos con `wire:model.live`, botón "Quitar el punto"). Todos con un `x-data` mínimo que emite `avytra:map-mount|unmount` para montarse y destruirse en los re-renders de Livewire, y `MutationObserver` sobre sus `data-*`.
- Explorar: propiedad `showMap` (`?mapa=true`), acción `toggleMap`, computed `mapPoints`, botón "Ver mapa / Ocultar mapa".
- `App\Livewire\LocationPickerComponent` (clase base de `pages::businesses.form` y `pages::listings.wizard`): hooks `updatedLocationLatitude/Longitude` → `manual_pin`, `clearLocationPoint`, `searchAddress` (limitador `geocode` por usuario, toasts), computed `municipalityCentre` y `geocoderAvailable`. Parcial `partials/location-fields` compartido (provincia, municipio, dirección, picker, visibilidad y callout "Sin punto en el mapa…").
- `LocationForm`: `geocoding_source`, `geocoding_provider`, `hasPoint()`, `willFallBackToMunicipality()`, `markManualPin()`, `clearPoint()`, `validateForAddressSearch()`, `geocodeAddress()`.
- Geocodificación: `App\Services\Geocoding\{Geocoder (con isAvailable()), GeocodingResult, NullGeocoder, NominatimGeocoder}`, excepción `App\Exceptions\GeocodingUnavailable`, binding en `AppServiceProvider` por `avytra.geocoding.driver`; Nominatim con `User-Agent`/`email` configurables, 1 petición/segundo mediante `RateLimiter` y caché de consultas normalizadas (también sin resultado) durante 30 días. Limitador nombrado `geocode`.
- `PublicListingPresenter::mapPoint()`; `SaveBusinessLocation` fija o limpia `geocoding_provider`/`geocoded_at` según el origen del punto.
- Config `avytra.map.{default_centre, zoom.{country, municipality, exact}}` y `avytra.geocoding.{rate_limit_per_hour, cache_days, nominatim.{base_url, user_agent, email, requests_per_second, timeout_seconds}}`; `.env.example` con `NOMINATIM_BASE_URL`, `NOMINATIM_USER_AGENT`, `NOMINATIM_EMAIL`.
- Tests: `Public/ListingMapTest` (HTML del mapa por visibilidad, nunca coordenadas privadas, mapa de explorar solo con los puntos públicos de la página), `Geocoding/NominatimGeocoderTest` (binding por driver, cabeceras y parámetros, caché, límite por segundo, fallo del proveedor); ampliados `Businesses/BusinessFormTest` (pin manual y derivación, quitar punto, búsqueda de dirección con driver null y con uno falso) y `Listings/ListingWizardTest` (paso 5 con pin exacto).
- 25 cadenas nuevas en `lang/es.json`.

### Cambiado
- El formulario de empresa y el paso 5 del wizard sustituyen el aviso "el mapa llegará pronto" por el picker real y comparten el parcial de ubicación; `location.municipality_id` y `location.location_visibility` pasan a `wire:model.live` para recentrar el mapa y mostrar el aviso de visibilidad.
- `Location` declara sus fechas como `CarbonImmutable` (coherente con `Date::use`).
- Documentación: docs 08 y 10 con notas de implementación, ADR-005 con el resultado del worker, `CLAUDE.md` (dependencia aprobada), STATUS.

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
