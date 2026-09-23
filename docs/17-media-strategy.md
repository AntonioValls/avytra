# 17 — Estrategia de medios (logo, portada, galería)

## Necesidades

Por empresa: logo opcional (cuadrado), portada recomendada (16:10), galería opcional (máx. 12, ordenable, con `alt`). Requisitos: validación MIME y tamaño, redimensionado, thumbnails para tarjeta/ficha/lightbox/OG, WebP, eliminación segura, orden, alt text, subida cómoda con `flux:file-upload`.

## Opciones evaluadas

| Opción | Pros | Contras |
|---|---|---|
| **A. Almacenamiento Laravel + modelo propio `business_images` + GD directo** (como `ImageOptimizer` de ParkingParaCamiones) | Sin dependencias | Hay que escribir conversiones, orden, borrado en cascada, colecciones; GD directo es verboso y propenso a fugas de memoria; ParkingParaCamiones acabó sin thumbnails por este coste |
| **B. Modelo propio + `intervention/image` v3** | Dependencia pequeña y conocida; control total del esquema | Sigue habiendo que escribir colecciones, orden, conversiones en cola, borrado |
| **C. `spatie/laravel-medialibrary` v11 (+ `spatie/image`)** | Resuelve colecciones (`logo`, `cover`, `gallery`), conversiones declarativas en cola, orden (`order_column`), `custom_properties` (alt), borrado en cascada, `srcset` responsive, disco configurable (local/S3); mantenimiento excelente (Spatie), muy usado con Livewire | Dependencia mayor (trae `spatie/image`, que usa GD o Imagick); tabla `media` polimórfica genérica; curva de aprendizaje mínima |

## Decisión (ADR-006): Spatie Media Library — aprobada al inicio de Phase 6 (2026-09-23)

Justificación: el 80 % del trabajo de medios (conversiones en cola, orden, colecciones, borrado seguro, responsive) ya está resuelto y probado; escribirlo a mano añade código propio sin valor diferencial y ParkingParaCamiones demuestra que la vía "GD a mano" termina recortando funcionalidad (una sola derivada de 1920 px para todo). El paquete se mantiene activamente, soporta Laravel 13 y se integra con uploads temporales de Livewire.

Condiciones:

- La dependencia se añade **solo en Phase 6** con aprobación explícita del propietario (regla del proyecto: no cambiar dependencias sin aprobación).
- Se instala `spatie/laravel-medialibrary` y se usa el driver GD (disponible) o Imagick si está en el servidor.
- Si se rechaza el paquete, se implementa la opción B con el mismo contrato de uso (`$business->cover()`, `$business->galleryImages()`), de modo que el resto de la aplicación no cambia.

## Diseño con Media Library

- `Business implements HasMedia`, trait `InteractsWithMedia`.
- Colecciones: `logo` (single file), `cover` (single file), `gallery` (múltiple, máx. 12 validado en el Form Object).
- Conversiones (en cola, formato WebP, calidad 82):
  - `thumb` 400×250 crop (tarjetas, miniaturas de galería, admin);
  - `card` 800×500 (tarjetas en pantallas retina);
  - `detail` 1600 ancho máx (ficha, lightbox);
  - `og` 1200×630 crop (Open Graph, solo `cover`);
  - `logo` 256×256 contain.
- `custom_properties.alt` editable; por defecto "{nombre de empresa}, imagen {n}".
- Orden de galería con `wire:sort` (Livewire 4) → `Media::setNewOrder()`.
- Disco: `public` en local; en producción `public` o S3-compatible mediante `MEDIA_DISK` (config del paquete). URLs a través de `getUrl('conversion')`.
- Subida: `flux:file-upload` + `wire:model` (Livewire temporary uploads) → validación → `addMedia($tmp)->toMediaCollection('gallery')`. Progreso y previsualización con `flux:file-item`.
- Eliminación: al borrar un `Media`, el paquete elimina archivos y conversiones; al borrar (soft) una empresa, los medios se conservan hasta borrado definitivo (force delete → cascada).
- El archivo original se conserva en disco (no público en listados; se usa para regenerar conversiones). Se **eliminan metadatos EXIF** en las conversiones (spatie/image lo hace al reencodificar); el original no se enlaza nunca desde HTML público.

## Validación

`image`, `mimes:jpg,jpeg,png,webp`, `max:8192`, `dimensions:min_width=600,min_height=400,max_width=8000,max_height=8000`. Logo: `min_width=128`. Sin SVG/GIF.

## Placeholders

Sin portada: bloque Mist con `avytra-symbol.svg` centrado y nombre del sector; misma proporción que la portada para evitar CLS.

## Rendimiento

- `<img>` con `width`/`height`, `srcset` (`thumb`/`card`) y `sizes`; `loading="lazy"` salvo portada de ficha.
- Conversiones en cola (`queue` configurada) para no bloquear el wizard; hasta que existan, se muestra el original redimensionado por CSS o el placeholder (la vista comprueba `hasGeneratedConversion`).
- Comando de regeneración disponible en el paquete (`media-library:regenerate`).

## Implementación (Phase 6)

- `spatie/laravel-medialibrary` ^11.23 (con `spatie/image` 3, driver GD). Config publicada en `config/media-library.php`: originales en `MEDIA_ORIGINALS_DISK` (`local`, privado), conversiones en `MEDIA_DISK` (`public`), `path_generator` propio (`businesses/{business_id}/{media_id}/`), conversiones en cola por defecto (`MEDIA_QUEUE_CONVERSIONS`), tras el commit.
- Números en `config/avytra.php` → `media`: `gallery_max` 12, `max_kilobytes` 8192, dimensiones mínimas (600×400; logo 128×128) y máximas (8000×8000), extensiones, `upload_rate_limit_per_hour` 30, `quality` 82 y las cinco conversiones (`thumb` 400×250 crop, `card` 800×500 crop, `detail` máx. 1600, `og` 1200×630 crop solo para `cover`, `logo` máx. 256).
- `Business implements HasMedia`: colecciones `logo`, `cover` (single file) y `gallery` desde el enum `App\Enums\MediaCollection`; helpers `cover()`, `logo()`, `galleryImages()`. El borrado suave de la empresa conserva los medios; `forceDelete` (borrado de cuenta) los elimina en cascada.
- Actions `App\Actions\Media\{AddBusinessImage, RemoveBusinessImage, ReorderBusinessGallery, UpdateBusinessImageAlt}`: nombre aleatorio, alt por defecto ("{empresa}, imagen N", "{empresa}, imagen de portada", "{empresa}, logo"), `GalleryFull` al superar el máximo, rechazo (404) de medios de otra empresa y audit `business.images_updated_by_admin` cuando actúa el superadmin.
- Componente Livewire `businesses.images` (paso 7 del wizard y formulario de empresa al editar): subida inmediata con `flux:file-upload` + `with-progress`, `wire:sort` en la galería, alt en línea (`wire:model.blur`), `wire:confirm` al quitar, limitador `image-upload`, `wire:poll.5s` mientras haya conversiones pendientes y estado "Procesando la imagen…".
- Público: `App\Support\Media\PublicImage` (solo URLs de conversión; `null` mientras la conversión no exista) y `PublicListingPresenter::{coverImage, galleryImages, logoImage, ogImageUrl}`; `RELATIONS` incluye `business.media`. Sin portada, la primera imagen de la galería hace de portada. Tarjetas con `srcset` `thumb`/`card`; ficha con `detail`, miniaturas y lightbox; `og:image` y `Offer.image`.
- Tests: `Media/BusinessImagesTest`, `Actions/Media/BusinessImageActionsTest`, `Public/ListingImagesTest` (con `Storage::fake` de ambos discos y conversiones reales con GD; `phpunit.xml` sube `memory_limit` a 1G). El seeder de demo no adjunta imágenes.
- Producción: `php artisan storage:link`, un worker de cola (`queue:work`) para las conversiones y, si se cambian los tamaños, `php artisan media-library:regenerate`.

## Tests previstos

- Subida válida crea medio en la colección correcta; inválida (tipo, tamaño, dimensiones) falla.
- Máximo 12 en galería.
- Orden persiste.
- Borrar imagen elimina archivos (con `Storage::fake`).
- La ficha usa URLs de conversión, nunca el original.
- Alt por defecto y editable.
