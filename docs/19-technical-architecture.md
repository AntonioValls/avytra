# 19 — Arquitectura técnica

## Filosofía

Laravel idiomático. Eloquent, Policies, Form Objects/Form Requests, enums, Actions con una responsabilidad clara, Jobs, Notifications, Events solo cuando aportan desacoplo real, Livewire + Flux para toda la UI. Sin capas Enterprise (repositorios, managers, interfaces para todo). Sin lógica de negocio en Blade ni en componentes Livewire: los componentes orquestan (autorizar → validar → llamar Action → notificar UI).

## Estructura de carpetas (se respeta la existente; no se crean carpetas base nuevas sin aprobación)

```text
app/
├── Actions/
│   ├── Fortify/                 (existente)
│   ├── Businesses/              CreateBusiness, UpdateBusiness, TransferBusinessOwnership
│   ├── Listings/                CreateListingDraft, PublishListing, PauseListing, ResumeListing,
│   │                            ConfirmListingAvailability, MarkListingAsSold, ArchiveListing,
│   │                            SuspendListing, UnsuspendListing, ExpireListing, ChangeListingSlug
│   ├── Locations/               SaveBusinessLocation (calcula public_*), 
│   ├── Reports/                 SubmitListingReport, ResolveListingReport
│   └── Users/                   CreateUserOnBehalf
├── Console/Commands/            ProcessListingFreshness, MakeSuperadmin, ImportSpanishGeography
├── Enums/                       (ver 04)
├── Exceptions/                  InvalidListingTransition
├── Http/
│   ├── Controllers/             Public: HomeController, ListingController (show), CategoryController,
│   │                            ProvinceController, SitemapController, RobotsController,
│   │                            LegalPageController (la confirmación de vigencia es una página Livewire autenticada)
│   ├── Middleware/              EnsureUserIsSuperadmin
│   └── Requests/                StoreListingReportRequest (si no va por Livewire)
├── Livewire/
│   ├── Actions/                 (existente: Logout)
│   └── Forms/                   BusinessForm, ListingWizard/{OperationStep, BasicInfoStep, ...}, ContactForm, ...
├── Concerns/                    (existente) PasswordValidationRules, ProfileValidationRules,
│                                TracksAuthorship, HasSlugHistory
├── Models/
│   └── ...                      User, Business, Listing, Location, OnlineProfile, Category, Region,
│                                Province, Municipality, ListingFinancialMetric, ListingEvent,
│                                ListingSlugRedirect, ListingReport, AuditLog
├── Notifications/               ListingFreshnessReminder, ListingExpired, ListingPublished,
│                                ListingSuspended, ListingReportReceived, WelcomeOnBehalf
├── Policies/                    BusinessPolicy, ListingPolicy, UserPolicy, ListingReportPolicy
├── Providers/                   AppServiceProvider (+ rate limiters), FortifyServiceProvider
├── Services/
│   └── Geocoding/               Geocoder (interface), GeocodingResult, NullGeocoder, NominatimGeocoder
└── Support/
    ├── Listings/                PublicListingPresenter (proyección pública), ListingTitleSuggester
    ├── Location/                PublicPointDeriver (jitter determinista, centroides)
    ├── Seo/                     PageMeta, JsonLd builders
    └── Audit/                   AuditLogger

config/avytra.php                configuración central (vigencia, mapa, geocoding, soporte, límites)
database/data/spain/             CSV de regiones, provincias, municipios (fuente documentada)
lang/es.json                     traducciones de UI (claves en inglés)
resources/views/
├── layouts/                     app (panel/admin), auth (existentes), public (nuevo)
├── pages/                       auth, settings (existentes), dashboard, businesses, listings (wizard),
│                                admin/*, public/* (componentes Livewire single-file ⚡ donde haya interacción)
├── components/                  listing-card, price, freshness-badge, map/*, public/*, empty-state
└── mail/                        plantillas de notificaciones con marca
resources/js/map/                módulo MapLibre (Phase 5)
resources/svg/                   logos
```

Convención Livewire 4 del starter kit: componentes single-file `⚡nombre.blade.php` bajo `resources/views/pages/...` registrados con `Route::livewire()`; componentes reutilizables con lógica en `resources/views/components/⚡*.blade.php`. Se sigue esta convención; clases en `app/Livewire/` solo para Forms y Actions.

## Flujo de una acción típica (ejemplo: pausar)

```text
Componente Livewire (pages::listings.index)
  └─ pause(int $listingId)
       ├─ $listing = Listing::findOrFail($listingId)
       ├─ $this->authorize('pause', $listing)               ← Policy
       ├─ app(PauseListing::class)->handle($listing, auth()->user())   ← Action
       │     ├─ throw_unless($listing->status->canTransitionTo(Paused), InvalidListingTransition)
       │     ├─ DB::transaction: status, paused_at, ListingEvent
       │     └─ (sin notificación)
       └─ Flux::toast('Publicación pausada')
```

Reglas:

- Los Actions reciben modelos ya autorizados y el actor (y opcionalmente `onBehalfOf`). Lanzan excepciones de dominio; el componente las traduce a mensajes.
- Los Actions no acceden a `request()` ni a `auth()` (reciben el actor). Facilita comandos y tests.
- Eventos de Laravel (`ListingPublished`, `ListingExpired`) solo si hay ≥2 listeners reales; en el MVP, las notificaciones se disparan desde el Action directamente. Se revisará al implementar la invalidación de cachés de sitemap (candidato natural a listener).

## Modelos

- `#[Fillable]` explícito; casts a enums; `CarbonImmutable` (ya configurado).
- Scopes de consulta con nombre de negocio: `Listing::publiclyVisible()`, `Listing::needingConfirmation()`, `Listing::dueForExpiration()`, `Business::ownedBy($user)`.
- Relaciones tipadas con generics en PHPDoc (Larastan).
- Métodos de estado derivado en el modelo (`needsConfirmation()`, `daysSinceConfirmation()`), sin efectos secundarios.
- Sin observers "mágicos" salvo `TracksAuthorship` y la derivación de coordenadas públicas (que se hace en el Action `SaveBusinessLocation`, no en observer, para que sea explícita).

## Livewire y Flux

- Livewire 4: `#[Url]` para filtros, `#[Computed]` para consultas en render, `#[Locked]` para IDs, `wire:navigate` en enlaces internos, Form Objects para validación, `wire:sort` para orden de galería, uploads temporales para imágenes.
- Flux-first: revisar la lista de componentes ([14-ui-design-system.md](14-ui-design-system.md)) antes de crear uno propio. Alpine solo para interacción puramente visual (lightbox, mostrar/ocultar).
- JS adicional: exclusivamente el módulo de mapa (MapLibre) cargado en páginas con mapa. Nada más sin ADR.
- Blaze: se mantiene instalado; se aplican sus directivas en componentes Blade puros de listado (tarjetas) cuando se mida beneficio (Phase 10), siguiendo la skill `blaze-optimize`.

## Internacionalización (preparación sin implementar idiomas)

- Todas las cadenas de UI pasan por `__()` con **claves en inglés natural** (convención del starter kit: `__('Dashboard')`) y traducción en `lang/es.json`. `APP_LOCALE=es`.
- Los enums exponen `label()` que usa `__()`.
- Contenido de usuario monolingüe.
- Rutas en español fijas en el MVP; cuando llegue la i18n se adoptará el patrón de rutas por locale de ParkingParaCamiones (`URL::resolveMissingNamedRoutesUsing`).
- Fechas con `Carbon::setLocale('es')` y `translatedFormat`; números con `NumberFormatter`/`Number::currency('EUR', locale: 'es')`.
- Moneda fija EUR; columna `currency` reservada.

## Zona horaria

`app.timezone = Europe/Madrid`. Los timestamps se almacenan en hora local de Madrid (comportamiento estándar de Laravel con esa config). Consecuencia aceptada: ambigüedad de una hora en el cambio horario de otoño, irrelevante para plazos de 45–60 días. Alternativa UTC + conversión en vistas descartada por complejidad innecesaria en un producto monopaís (ADR-008).

## Colas y scheduler

- `QUEUE_CONNECTION=database` en local; en producción `database` o `redis` según hosting. Worker supervisado (`php artisan queue:work`) y cron `schedule:run` cada minuto. Se documenta en README de despliegue en Phase 10.
- Jobs: notificaciones, conversiones de imagen, geocodificación.
- Scheduler: `avytra:listings:process-freshness` (hourly), `media-library:clean` opcional, limpieza de reportes resueltos antiguos (roadmap).

## Caché

- `CACHE_STORE=database` (local). Uso limitado: recuentos por categoría/provincia (15 min), sitemap (1 h, invalidación por eventos de publicación), rate limiters.

## Búsqueda

SQL: `LIKE` sobre título y nombre + filtros por columnas indexadas + `whereHas` con joins. Preparado para FULLTEXT en MySQL. Sin Scout ni motores externos; los modelos podrían añadir `Searchable` sin cambios estructurales.

## Calidad

- `composer test` = Pint + Larastan + Pest (existente). No se baja el nivel de Larastan.
- `.ai/rules/` no existe hoy; si el propietario decide usarlo (Boost `record-rule`), se creará solo a petición explícita.

## Decisiones que requieren aprobación antes de ejecutarse

1. Añadir `maplibre-gl` (npm) en Phase 5.
2. Añadir `spatie/laravel-medialibrary` en Phase 6.
3. Cualquier otro paquete.
