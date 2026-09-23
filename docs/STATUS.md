# AVYTRA — Project Status

## Current phase

**Phase 5 — Ubicación y mapas: implementada, pendiente de revisión visual del propietario** (última tarea de la Definition of Done; en especial el picker del wizard y del formulario de empresa, que requiere sesión iniciada). Al aprobarla, comienza Phase 6 — Medios (requiere aprobar `spatie/laravel-medialibrary`).

Phase 4 se dio por aprobada el 2026-09-23 al pedir el propietario el inicio de Phase 5; esa misma petición se tomó como aprobación de la dependencia `maplibre-gl` (ADR-005).

## Completed

### Phase 0 — Documentación y arquitectura (2026-09-21)
- Documentos `docs/00` a `docs/22`, `DECISIONS.md` (ADR-001…016), `CHANGELOG.md`, `CLAUDE.md`. Análisis de ParkingParaCamiones.

### Phase 1 — Fundamentos (2026-09-21)
- Configuración, marca, layouts (`public`, `app` con área admin, `auth`), traducción, roles y comando `avytra:superadmin`, middleware `superadmin` (404), panel `/panel`, `/admin`, tabla `audit_logs` + `AuditLogger`, rate limiters `register` y `public`. Detalle en `CHANGELOG.md`.

### Phase 2 — Dominio de empresas (2026-09-22)
- Modelo `Business` + `Location` + `OnlineProfile`, catálogo geográfico y de sectores, `BusinessPolicy`, Actions de empresa, `PublicPointDeriver`, panel y admin de empresas. Detalle en `CHANGELOG.md`.

### Phase 3 — Publicaciones (2026-09-22)
- Tablas de publicaciones, enums, `ListingPolicy`, Actions de ciclo de vida, validador de publicabilidad, slugger, wizard de 8 pasos, panel y admin de publicaciones, `DemoBusinessSeeder`. Detalle en `CHANGELOG.md`.

### Phase 4 — Marketplace público (2026-09-22)
- `PublicListingPresenter` (única proyección pública), `PriceFormatter`, `MarketplaceAggregates`, home real, explorar con filtros en URL, ficha con contacto revelado bajo rate limit, reportes con bandeja admin, páginas estáticas y errores públicos. Detalle en `CHANGELOG.md`.

### Phase 5 — Ubicación y mapas (2026-09-23)
- Dependencia `maplibre-gl` ^6.11 aprobada (ADR-005). Entrada Vite separada `resources/js/map.js` + módulo `resources/js/map/` (`support`, `tokens`, `geometry`, `layers`, `listing`, `explore`, `picker`). El worker de MapLibre se resuelve con `?worker&url` + `setWorkerUrl()`: Vite lo empaqueta y lo sirve, sin archivos vendorizados. Verificado en navegador con el build y con `npm run dev`.
- Componentes Blade `x-map.listing` (ficha y vista previa del wizard: pin para `exact`, círculo para `approximate`/`city_only`, nada para `hidden`; fallback HTML sin WebGL con enlace a OpenStreetMap sobre coordenadas públicas), `x-map.explore` (toggle "Ver mapa", `?mapa=true`, solo los `mapPoint()` de la página actual) y `x-map.picker` (pin arrastrable/clic, centrado en el municipio, inputs ocultos con `wire:model.live`, "Quitar el punto").
- `App\Livewire\LocationPickerComponent` (clase base del formulario de empresa y del wizard) + parcial `partials/location-fields` compartido; `LocationForm` con `geocoding_source`/`geocoding_provider`, `markManualPin()`, `clearPoint()`, `willFallBackToMunicipality()` (callout de aviso) y `geocodeAddress()`. `SaveBusinessLocation` mantiene `geocoded_at`/proveedor coherentes con el origen.
- `App\Services\Geocoding\{Geocoder, GeocodingResult, NullGeocoder, NominatimGeocoder}` + `GeocodingUnavailable`; binding por `avytra.geocoding.driver`; botón "Buscar la dirección en el mapa" solo con driver ≠ null, limitador `geocode` por usuario; Nominatim con `User-Agent`, 1 petición/segundo y caché de 30 días.
- `PublicListingPresenter::mapPoint()`. Config `avytra.map.{default_centre, zoom.*}` y `avytra.geocoding.{rate_limit_per_hour, cache_days, nominatim.*}`; `.env.example` con `NOMINATIM_*`.
- 25 cadenas nuevas en `lang/es.json`.

## In progress

- Nada.

## Next

- Revisión visual del propietario (requiere `npm run build` o `composer run dev`): ficha de demo (`/empresas/venta-de-tienda-online-de-consumibles-de-impresoras-en-borriana`) con el círculo del municipio, `/empresas?mapa=true` y el toggle, y sobre todo el picker: editar la empresa de demo en `/panel/empresas`, hacer clic en el mapa, arrastrar el pin, "Quitar el punto", cambiar la visibilidad y guardar; luego repetir en el paso 5 del wizard y comprobar que la ficha pública pasa a mostrar pin (exact) o círculo de 700 m (approximate). Opcional: poner `GEOCODING_DRIVER=nominatim` en `.env` y probar "Buscar la dirección en el mapa". Comprobado en esta sesión con el navegador integrado (sin sesión iniciada): ficha y explorar con build y con `npm run dev`, toggle del mapa por re-render Livewire (montaje/desmontaje), navegación `wire:navigate` entre ficha y explorar, versión móvil sin scroll horizontal y sin errores de consola. El picker solo está verificado con tests (no se inicia sesión desde el navegador automatizado).
- Phase 6 — Medios: aprobar `spatie/laravel-medialibrary`.

## Blockers

- Ninguno. Aprobación pendiente en su fase: `spatie/laravel-medialibrary` (Phase 6).
- Notas aceptadas de Phase 5:
  - `Geocoder::isAvailable()` se añade a la interfaz de docs/10 para que los formularios oculten el botón sin comprobar la clase del driver.
  - La lógica compartida del picker vive en una clase base (`LocationPickerComponent extends Livewire\Component`) y no en un trait: Larastan no analiza traits usados solo desde componentes single-file (fuera de sus `paths`).
  - El módulo de mapa se monta desde un `x-data` mínimo (`init`/`destroy` → eventos `avytra:map-mount|unmount`) además de `livewire:navigated`: es lo que permite crear y destruir mapas que aparecen en un re-render de Livewire. Los datos posteriores llegan por atributos `data-*` observados con `MutationObserver`.
  - `npm run dev` deja `public/hot` si el proceso se mata sin señal (por ejemplo, al parar el servidor de vista previa del agente); borrarlo si las páginas intentan cargar de `localhost:5173` sin Vite arrancado.
  - Notas de Phase 4 que siguen vigentes: rutas del panel `panel.listings.*`; `tipo`/`operacion` en explorar; `robots.txt`, sitemap, `ItemList` y 301 de `?sector=` en Phase 9; `MarketplaceAggregates` cachea arrays planos; el compilador single-file no admite `#[Layout]` delante de `new class`; `throttle:public` no cubre `/livewire/update`.
  - Notas de Phase 3 que siguen vigentes: `title`/`slug` nullables hasta publicar; `ExpireListing` no notifica (Phase 7); el limitador `register` se aplica en Phase 10.

## Important decisions

Ver `docs/DECISIONS.md` (ADR-001…017; ADR-005 actualizado con el resultado del worker). Decisiones menores de Phase 5 en las notas de "Blockers".

## Last tests executed

- 2026-09-23 — `composer test` (Pint + Larastan nivel 7 + Pest): **410 tests, todo en verde** (393 de Phase 4 más 17 nuevos). Nuevos: `Public/ListingMapTest`, `Geocoding/NominatimGeocoderTest`; ampliados `Businesses/BusinessFormTest` (pin, quitar punto, búsqueda de dirección) y `Listings/ListingWizardTest` (paso 5 con pin).

## Last updated

2026-09-23 — Phase 5 implementada, verificada con tests y en navegador (público); pendiente de revisión visual del picker por el propietario.
