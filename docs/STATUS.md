# AVYTRA — Project Status

## Current phase

**Phase 7 — Sistema de vigencia: implementada y verificada (tests y navegador), pendiente de revisión del propietario.** Al aprobarla, comienza Phase 8 — Administración y asistencia. Fases cerradas: 0 a 7 (8 de 11); quedan 8, 9 y 10.

Phase 6 se dio por aprobada el 2026-09-26 al pedir el propietario el inicio de Phase 7 (sin dependencias nuevas). Phase 5 se dio por aprobada el 2026-09-23 al pedir el inicio de Phase 6; esa petición se tomó como aprobación de `spatie/laravel-medialibrary` (ADR-006), igual que `maplibre-gl` en Phase 5.

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

### Phase 7 — Sistema de vigencia (2026-09-26)
- Comando `avytra:listings:process-freshness` (`--dry-run`), scheduler hourly `withoutOverlapping`, enum `ReminderStage`, Actions `SendListingFreshnessReminder`, `ResendFailedReminder` y `ExpireListing` con notificación; notificaciones `ListingFreshnessReminder` (dos etapas) y `ListingExpired` con `failed()` → evento `reminder_failed`; tema de correo `avytra`.
- `ConfirmationLink` (firma temporal, validación sobre la URL canónica) y página autenticada `/panel/publicaciones/{listing}/confirmar` con limitador `confirmation`; botón "Sigue disponible" visible en el panel; resumen operativo real en `/admin`, filtros de caducadas y avisos fallidos, reenvío desde el detalle. Detalle en `CHANGELOG.md` y `docs/13`.
- Verificado en el navegador integrado con el usuario superadmin sembrado: email de primer aviso con el tema de marca; enlace firmado → login → aterrizaje en la página de confirmación (`intended`); clic en "Sí, sigue disponible" → "Gracias, tu publicación sigue vigente hasta el …"; resumen operativo con contadores y listas; detalle admin; móvil sin scroll horizontal; sin errores de consola propios.

## In progress

- Nada.

## Next

- Revisión del propietario de Phase 7. Para probar en local: `php artisan avytra:listings:process-freshness --dry-run` (lista lo que haría), `php artisan schedule:list`, y con `MAIL_MAILER=log` los emails quedan en `storage/logs/laravel.log`. Para ver un aviso real: poner `last_confirmed_at` de una publicación 45 días atrás, ejecutar el comando sin `--dry-run` con un worker de cola (`php artisan queue:work`) y abrir el enlace del email. La publicación de demo se confirmó durante la verificación (día 0 otra vez).
- Pendiente de revisión visual del propietario desde Phase 6: paso 7 del wizard y sección de imágenes del formulario de empresa (subida, reordenación, alt, borrado; requiere worker de cola).
- Phase 8 — Administración y asistencia.

## Blockers

- Ninguno.
- Notas aceptadas de Phase 7:
  - Cada ejecución del comando envía como máximo un email por publicación: entre el segundo umbral y la pausa solo se manda el segundo aviso (marcando ambos flags); por encima de la pausa se pausa sin avisos (docs/13). Tras un scheduler parado varios días, una publicación puede pasar a `expired` sin avisos previos: el email de pausa explica cómo reactivar.
  - Las notificaciones en cola no admiten `ShouldBeUnique`; las barreras contra duplicados son el flag escrito antes de encolar y `withoutOverlapping()`.
  - `ConfirmationLink::isValid()` recalcula la firma sobre `route('panel.listings.confirm', [...])`, no sobre la URL de la petición: funciona detrás de proxies y en las peticiones de Livewire (y en `Livewire::withQueryParams()` en tests). Con enlace caducado la página no ofrece el botón (403 si se invoca la acción) y remite al panel, donde el botón sigue disponible.
  - El `role` del usuario `test@example.com` puede perderse al recrear la base de datos local; se restaura con `php artisan avytra:superadmin test@example.com`.
  - En producción hacen falta el scheduler (`php artisan schedule:run` cada minuto) y un worker de cola para que salgan los emails.
- Notas aceptadas de Phase 6:
  - El original subido nunca se sirve: vive en el disco privado y solo se usa para (re)generar conversiones (`php artisan media-library:regenerate` si cambian los tamaños). En producción hace falta `php artisan storage:link` y un worker de cola.
  - `PublicImage::fromMedia()` devuelve `null` mientras falte alguna conversión pedida: las vistas públicas muestran el placeholder hasta que la cola termina; el componente del panel muestra "Procesando la imagen…" y hace `wire:poll.5s`.
  - Sin portada, la primera imagen de la galería hace de portada en tarjetas y ficha (pero no de `og:image`).
  - La galería se guarda al elegir cada archivo (no hay botón de guardar): el paso 7 del wizard no persiste nada al avanzar y el formulario de empresa muestra la sección de imágenes fuera del `<form>`, solo al editar.
  - `config/media-library.php` mantiene `temporary_upload_model` como cadena (Media Library Pro no está instalado) para que Larastan analice `config/`.
  - `phpunit.xml` fija `memory_limit=1G`: las conversiones con GD a lo largo de toda la suite agotan los 128M del CLI.
  - Notas de Phase 5 que siguen vigentes: `Geocoder::isAvailable()`; `LocationPickerComponent` como clase base; montaje del mapa por `x-data`; borrar `public/hot` si queda huérfano.
  - Notas de Phase 4 que siguen vigentes: rutas del panel `panel.listings.*`; `tipo`/`operacion` en explorar; `robots.txt`, sitemap, `ItemList` y 301 de `?sector=` en Phase 9; `MarketplaceAggregates` cachea arrays planos; el compilador single-file no admite `#[Layout]` delante de `new class`; `throttle:public` no cubre `/livewire/update`.
  - Notas de Phase 3 que siguen vigentes: `title`/`slug` nullables hasta publicar; el limitador `register` se aplica en Phase 10 (`ExpireListing` notifica desde Phase 7).

## Important decisions

Ver `docs/DECISIONS.md` (ADR-001…017; ADR-006 aceptado con resultado). Decisiones menores de Phase 6 en las notas de "Blockers".

## Last tests executed

- 2026-09-26 — `composer test` (Pint + Larastan nivel 7 + Pest): **463 tests, todo en verde** (436 de Phase 6 más 27 nuevos). Nuevos: `Freshness/ProcessListingFreshnessTest`, `Freshness/FreshnessNotificationsTest`, `Listings/ListingConfirmationPageTest`, `Console/ScheduleTest`, `Admin/AdminSummaryTest`; ampliado `Admin/AdminListingsTest`.

## Last updated

2026-09-26 — Phase 7 implementada, verificada con tests y en navegador; pendiente de revisión del propietario.
