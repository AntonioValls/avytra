# AVYTRA — Project Status

## Current phase

**Phase 6 — Medios: implementada, pendiente de revisión visual del propietario** (última tarea de la Definition of Done; en especial el paso 7 del wizard y la sección de imágenes del formulario de empresa, que requieren sesión iniciada). Al aprobarla, comienza Phase 7 — Sistema de vigencia (sin dependencias nuevas).

Phase 5 se dio por aprobada el 2026-09-23 al pedir el propietario el inicio de Phase 6; esa misma petición se tomó como aprobación de la dependencia `spatie/laravel-medialibrary` (ADR-006), igual que se hizo con `maplibre-gl` en Phase 5.

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
- `maplibre-gl` (ADR-005), componentes `x-map.listing`, `x-map.explore` y `x-map.picker`, `LocationPickerComponent`, geocodificación (`NullGeocoder`, `NominatimGeocoder`), `PublicListingPresenter::mapPoint()`. Detalle en `CHANGELOG.md`.

### Phase 6 — Medios (2026-09-23)
- `spatie/laravel-medialibrary` ^11.23 (ADR-006, driver GD). Originales en disco privado (`MEDIA_ORIGINALS_DISK=local`), conversiones WebP en el público (`MEDIA_DISK=public`) bajo `businesses/{business_id}/{media_id}/`; conversiones en cola tras el commit (`MEDIA_QUEUE_CONVERSIONS`).
- `Business implements HasMedia`: colecciones `logo`, `cover` (un archivo) y `gallery` (máx. 12) del enum `MediaCollection`; conversiones `thumb`, `card`, `detail`, `og` (solo portada) y `logo` con tamaños en `config/avytra.php` → `media`.
- Actions `App\Actions\Media\*` (añadir, quitar, reordenar, alt) con audit del superadmin; componente Livewire `businesses.images` en el paso 7 del wizard y en el formulario de empresa (subida inmediata con `flux:file-upload`, `wire:sort`, alt en línea, limitador `image-upload`, `wire:poll` mientras haya conversiones pendientes).
- Público: `PublicImage` + `PublicListingPresenter::{coverImage, galleryImages, logoImage, ogImageUrl}`; tarjetas con `srcset`, ficha con galería y lightbox, `og:image`, `Offer.image`; placeholder de marca sin imágenes o mientras la cola no ha generado la conversión.
- Verificado en el navegador integrado con imágenes generadas sobre la empresa de demo (ficha: portada, miniaturas, lightbox con flechas y pie; explorar: tarjeta con portada; móvil sin scroll horizontal; sin errores de consola; solo URLs `.webp` de conversión en el HTML). El paso 7 y el formulario solo están verificados con tests.

## In progress

- Nada.

## Next

- Revisión visual del propietario (requiere `npm run build` o `composer run dev` y un worker de cola: `php artisan queue:work` o `composer run dev`): en `/panel/empresas` editar la empresa de demo y, al final del formulario, subir portada, varias fotos de galería y un logo; arrastrar para reordenar, editar un alt, quitar una imagen; comprobar el estado "Procesando la imagen…" hasta que el worker genere las conversiones. Repetir en el paso 7 del wizard (`/panel/publicaciones/{id}/editar?paso=7`) y ver la portada en la vista previa del paso 8 y en la ficha pública. La empresa de demo ya tiene cuatro imágenes generadas (portada verde, dos de galería y un logo) que se pueden quitar desde el formulario.
- Phase 7 — Sistema de vigencia (comando `avytra:listings:process-freshness`, recordatorios, confirmación de un clic, pausa automática). Sin dependencias nuevas.

## Blockers

- Ninguno.
- Notas aceptadas de Phase 6:
  - El original subido nunca se sirve: vive en el disco privado y solo se usa para (re)generar conversiones (`php artisan media-library:regenerate` si cambian los tamaños). En producción hace falta `php artisan storage:link` y un worker de cola.
  - `PublicImage::fromMedia()` devuelve `null` mientras falte alguna conversión pedida: las vistas públicas muestran el placeholder hasta que la cola termina; el componente del panel muestra "Procesando la imagen…" y hace `wire:poll.5s`.
  - Sin portada, la primera imagen de la galería hace de portada en tarjetas y ficha (pero no de `og:image`).
  - La galería se guarda al elegir cada archivo (no hay botón de guardar): el paso 7 del wizard no persiste nada al avanzar y el formulario de empresa muestra la sección de imágenes fuera del `<form>`, solo al editar.
  - `config/media-library.php` mantiene `temporary_upload_model` como cadena (Media Library Pro no está instalado) para que Larastan analice `config/`.
  - `phpunit.xml` fija `memory_limit=1G`: las conversiones con GD a lo largo de toda la suite agotan los 128M del CLI.
  - Notas de Phase 5 que siguen vigentes: `Geocoder::isAvailable()`; `LocationPickerComponent` como clase base; montaje del mapa por `x-data`; borrar `public/hot` si queda huérfano.
  - Notas de Phase 4 que siguen vigentes: rutas del panel `panel.listings.*`; `tipo`/`operacion` en explorar; `robots.txt`, sitemap, `ItemList` y 301 de `?sector=` en Phase 9; `MarketplaceAggregates` cachea arrays planos; el compilador single-file no admite `#[Layout]` delante de `new class`; `throttle:public` no cubre `/livewire/update`.
  - Notas de Phase 3 que siguen vigentes: `title`/`slug` nullables hasta publicar; `ExpireListing` no notifica (Phase 7); el limitador `register` se aplica en Phase 10.

## Important decisions

Ver `docs/DECISIONS.md` (ADR-001…017; ADR-006 aceptado con resultado). Decisiones menores de Phase 6 en las notas de "Blockers".

## Last tests executed

- 2026-09-23 — `composer test` (Pint + Larastan nivel 7 + Pest): **436 tests, todo en verde** (410 de Phase 5 más 26 nuevos). Nuevos: `Media/BusinessImagesTest`, `Actions/Media/BusinessImageActionsTest`, `Public/ListingImagesTest`.

## Last updated

2026-09-23 — Phase 6 implementada, verificada con tests y en navegador (público); pendiente de revisión visual del paso 7 y del formulario de empresa por el propietario.
