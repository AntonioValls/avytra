# 22 — Referencia: análisis de ParkingParaCamiones

Repositorio inspeccionado (solo lectura) en `C:\Users\anton\VisualStudio Projects\parkingparacamiones` el 2026-09-21. AVYTRA es un proyecto independiente: aquí se documenta qué se toma **conceptualmente**, qué se rehace y qué no se traslada. No se copia código sin adaptarlo.

## Stack de ParkingParaCamiones

Laravel 13, Livewire 4 (SFC `⚡`), Flux Pro 2.17+, Blaze, Fortify con passkeys, Cashier (Stripe), Pest 5, Larastan, Pint. JS: `maplibre-gl` ^6.6, Tailwind 4, TipTap (editor de noticias). **Sin** paquete de medios, SEO, sitemap, geocodificación ni Scout. Mismo starter kit y mismas herramientas que AVYTRA, lo que facilita reutilizar patrones.

## Hallazgos por área

### Mapa

- MapLibre GL JS con estilo de OpenFreeMap (`https://tiles.openfreemap.org/styles/liberty`), sin API key. URL y colores de marca **hardcodeados** en dos archivos JS casi duplicados (`resources/js/map.js`, `resources/js/parkings-map.js`).
- Worker de MapLibre vendorizado a mano en `public/vendor/maplibre/` por incompatibilidad con Vite; requiere regenerar en cada actualización (documentado en `.ai/rules/js.md`). Riesgo de desincronización sin cobertura.
- Mapa global con clustering nativo (GeoJSON source) alimentado por un endpoint público que devuelve **todas** las coordenadas de todos los parkings publicados.
- Mapa de detalle con un marcador y popup.
- `resources/js/map-support.js`: detección de WebGL2, `createMap()` con try/catch y `webglcontextlost`, y fallback HTML servido por el servidor. **Excelente**; probado a nivel HTML.
- Mapa totalmente desacoplado de Livewire (lectura de `data-*`, init en `DOMContentLoaded` y `livewire:navigated`, guarda `dataset.mapInitialized`).

### Coordenadas y ubicaciones

- `parkings`: `province_id` FK, `address`, `city` (texto libre), `postal_code`, `latitude/longitude decimal(10,7)`, `google_maps_url`. Sin país, sin índices geográficos.
- `regions` y `provinces` como tablas con SEO propio y tablas de traducción; **sin municipios** (city como string libre → fragmentación "Valencia/València").
- Datos territoriales en arrays PHP dentro de seeders; slugs byte-exactos con URLs legacy de WordPress.
- **Sin geocodificación**: coordenadas por regex sobre URLs de Google Maps (`@lat,lng`, que es el centro del viewport, no el lugar), seeder manual de 5 registros, o dos inputs numéricos en el formulario del propietario.
- **Sin consultas espaciales** (ni radio, ni bbox, ni Haversine).

### Búsqueda y filtros

- No hay buscador con filtros; navegación por taxonomía (provincia, región, servicio) y un command palette (`flux:command`, Ctrl+K) con `LIKE '%term%'` sobre nombre/ciudad/provincia.
- **Sin paginación** en listados públicos (`->get()` de todo con eager loading): riesgo de rendimiento a partir de miles de filas.
- Orden con `orderByRaw` CASE por plan de pago en cada consulta.

### Tarjetas y fichas

- `x-public.parking-card` Blade puro: portada 16:9 con `width/height` y `loading="lazy"`, placeholder SVG, nombre, pills de servicios, provincia/región, horario, plazas, ribbon "Destacado" etiquetado como publicidad.
- Ficha en controlador clásico + Blade de 428 líneas que mezcla metadatos, JSON-LD y layout; lightbox Alpine sin librería (`x-teleport`, collage 1+4, "+N fotos"); sidebar con contacto, "Cómo llegar", reportar datos incorrectos, reclamar; fecha de verificación y procedencia visibles (confianza).

### SEO (lo más sólido del proyecto)

- Slugs únicos con sufijo, comprobando soft-deleted; `getRouteKeyName = slug`.
- Rutas replicadas por locale (`es` raíz, `/en`, `/ru`) con `URL::resolveMissingNamedRoutesUsing` para que `route('home')` resuelva el locale actual; `LocaleUrls::alternates()` para hreflang.
- `partials/head` con title, description, canonical, hreflang + `x-default`, robots opcional, Open Graph completo. Sin Twitter cards.
- JSON-LD construido a mano y renderizado en `<body>` vía `@stack('jsonld')` porque `wire:navigate` conserva scripts del `<head>` y los acumula. Test que cuenta ocurrencias.
- `noindex,follow` + exclusión de sitemap y footer para provincias sin parkings; `LISTING_CACHE_KEYS` en el modelo con invalidación en `saved/deleted/restored`.
- Sitemap manual cacheado 1 h con `xhtml:link` por locale; robots.txt estático con dominio absoluto hardcodeado.
- Tabla `redirects` + `Route::fallback` para URLs legacy.

### Imágenes

- Disco `public`; subida con `wire:model`; `ImageOptimizer` con GD puro → WebP 1920 px, sin thumbnails (misma imagen para tarjeta, collage y lightbox); `width/height` calculados al crear; alt por defecto y editable; flujo de revisión pendiente para ediciones de propietarios.

### Auth, roles, admin

- Fortify + passkeys; `teams` como unidad de tenencia y facturación; `TeamRole` enum sin paquete de permisos; `users.is_super_admin` booleano con `Gate::before`; middleware `EnsureSuperAdmin` responde **404**; Actions por transición (`Approve/Reject/Claim/Transfer/SetPlan`) con notificaciones; invariante documentada de que `team_id` solo se escribe vía `TransferParking`.

### Tests

Pest 5, 57 archivos, factories completas; buena cobertura de SEO (canonical, hreflang, JSON-LD único, noindex, invalidación de caché), mapa (GeoJSON excluye no publicados y sin coordenadas, orden `[lng, lat]`, fallback), búsqueda, galería, optimizador. Sin tests de JS.

## Qué reutiliza AVYTRA (conceptualmente)

1. **MapLibre + OpenFreeMap**, con la URL de estilo en config y atribución. (ADR-005)
2. **`map-support.js`** (fallback WebGL) casi tal cual, adaptado a módulo único.
3. **Desacoplo mapa ↔ Livewire** con `data-*` y `livewire:navigated`.
4. **Patrón SEO**: `noindex` en taxonomías vacías, JSON-LD en body con test contador, sitemap manual cacheado con invalidación por eventos, redirecciones de slug, robots por ruta (corrigiendo el dominio hardcodeado), breadcrumbs visibles + `BreadcrumbList`.
5. **Tarjeta Blade pura** con `width/height` + lazy + placeholder SVG; **lightbox Alpine** sin librería.
6. **Actions por transición** con notificación y guardas de invariantes; **superadmin con 404**; enum de roles sin paquete.
7. **Señales de confianza visibles** (fecha de verificación) → en AVYTRA, "Disponibilidad confirmada hace N días".
8. Cuando llegue i18n: rutas por locale y `*_translations` para catálogos.
9. Etiquetar como publicidad cualquier destacado de pago (si algún día se monetiza).

## Qué se rehace o se hace distinto

1. **Modelo de ubicación completo**: país, municipio como tabla con centroides (desde datos, no arrays PHP), índices, coordenadas privadas vs públicas derivadas, `location_visibility`. ParkingParaCamiones no tiene nada de esto y AVYTRA lo necesita desde el día 1.
2. **Nunca un endpoint público con todas las coordenadas**: el mapa de explorar recibe solo la página actual y solo `public_*`.
3. **Sin regex sobre URLs de Google Maps ni inputs numéricos de lat/lng**: pin arrastrable sobre el mapa + centroides + `Geocoder` abstracto opcional.
4. **Paginación siempre** en público.
5. **Búsqueda con filtros reales** (columnas indexadas, joins) en lugar de LIKE sobre todo; FULLTEXT cuando toque.
6. **Un solo módulo de mapa y un componente Blade**; colores y estilo desde config/tokens; sin worker vendorizado a mano (resolver en build o verificar que ya no es necesario con Vite 8).
7. **Conversiones de imagen por tamaño** (thumb/card/detail/og) — probablemente con Media Library (ADR-006).
8. **Ficha con presentador/`PageMeta`**, no 400 líneas de Blade con `@php`.
9. **Claves de traducción en inglés** y sin strings de UI dentro de JS.
10. **Datos territoriales desde archivos** con fuente y licencia documentadas.
11. **Sin teams ni Stripe** en el MVP: `owner_user_id` directo.

## Decisiones de ParkingParaCamiones que NO se trasladan

- Tile URL y colores hardcodeados en JS.
- Dependencia de OpenFreeMap sin ruta de salida configurada (AVYTRA la deja configurable).
- Worker de MapLibre copiado a `public/` a mano.
- Coordenadas por regex de URLs.
- Seeders con datos de producción en arrays PHP.
- Ciudad como texto libre.
- Ausencia de índices geográficos y de paginación.
- Robots.txt con dominio absoluto.
- Mezcla de idiomas en claves de traducción y comentarios.
- Endpoint JSON público masivo de coordenadas.
- Controlador llamado "Search" sin búsqueda.
