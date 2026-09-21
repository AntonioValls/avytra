# 15 — SEO

## Principios

- Solo se indexa contenido real y útil: publicaciones publicadas, categorías y provincias **con** publicaciones, páginas estáticas con texto propio.
- No se generan páginas geográficas o de categoría vacías.
- Las URLs son en español, estables y con historial de redirecciones.
- La estructura queda preparada para localización futura de rutas (ParkingParaCamiones ya resolvió este problema; se toma su patrón cuando llegue el momento, no antes).

## URLs

| Página | URL | Indexable |
|---|---|---|
| Home | `/` | Sí |
| Explorar | `/empresas` | Sí (sin filtros); con query string → `canonical` a la versión sin filtros salvo `sector`/`provincia`, que tienen URL propia |
| Ficha | `/empresas/{slug}` | Sí si `published`; `sold` sí durante `sold_visible_days` |
| Categoría | `/empresas/categoria/{slug}` | Sí si ≥1 publicación visible; si no, `noindex,follow` y fuera del sitemap |
| Provincia | `/empresas/provincia/{slug}` | Ídem |
| Online | `/negocios-online` | Sí si ≥1 |
| Publicar, cómo funciona, legales | `/publicar`, `/como-funciona`, `/aviso-legal`… | Sí |
| Panel, admin, auth | `/panel/*`, `/admin/*`, `/login`… | `noindex` + `Disallow` en robots |

Subcategorías y municipios no tienen URL propia en el MVP (se filtran por query string, `noindex`). Se añadirán cuando haya volumen (roadmap).

## Slugs

- Generados desde `listings.title` con `Str::slug`, únicos con sufijo numérico (`-2`, `-3`), comprobando también registros borrados lógicamente y `listing_slug_redirects`.
- Se generan al crear el borrador (título sugerido) y se **regeneran al publicar por primera vez** si el título cambió durante el wizard. Tras publicar, cambiar el título **no** cambia el slug automáticamente; el propietario puede pedir "actualizar la URL" (acción explícita) y entonces el slug antiguo se guarda en `listing_slug_redirects` → 301 permanente.
- El superadmin puede editar el slug directamente (con redirección automática).
- Nunca se reutiliza un slug antiguo para otra publicación.
- Slugs reservados: los que colisionen con rutas (`categoria`, `provincia`) se evitan con validación.

## Metadatos por página

Se centralizan en un View Model / clase `App\Support\Seo\PageMeta` (title, description, canonical, robots, og:*, jsonLd[]) que el layout público renderiza. Ninguna vista construye `<meta>` a mano.

| Página | title | description |
|---|---|---|
| Ficha | "{título} · {precio o Consultar} · AVYTRA" (≤ 60 car.) | `tagline` o primeros 155 caracteres de la descripción |
| Categoría | "{Sector} en venta y traspaso · AVYTRA" | texto de `categories.description` |
| Provincia | "Empresas en venta en {provincia} · AVYTRA" | generado con recuento y sectores presentes |
| Explorar | "Empresas y negocios en venta o traspaso · AVYTRA" | fija |
| Home | "AVYTRA — Empresas que cambian de manos" | fija |

Open Graph: `og:type` (`website` / `article` para ficha), `og:title`, `og:description`, `og:url`, `og:image` (portada 1200×630 generada como conversión, o imagen de marca por defecto), `og:locale = es_ES`, `og:site_name`. Twitter card `summary_large_image`.

## Schema.org (JSON-LD)

- Ficha: `Offer` con `itemOffered` de tipo `Organization` (o `LocalBusiness` para físicos con `exact`), `price`/`priceCurrency` solo si `price_disclosure = exact`, `priceSpecification` con `minPrice/maxPrice` si rango, `availability` (`InStock` publicada / `SoldOut` vendida), `areaServed`/`address` **solo con localidad y provincia** (nunca dirección ni coordenadas privadas; `GeoCoordinates` solo con `exact`), `image[]`, `datePosted`, `validThrough` (= `next_confirmation_at`). Más `BreadcrumbList`.
- Categoría/provincia: `ItemList` de las publicaciones de la página + `BreadcrumbList`; omitido si vacío.
- Home: `WebSite` con `SearchAction` apuntando a `/empresas?q={search_term_string}` y `Organization` (AVYTRA).
- El JSON-LD se renderiza **dentro de `<body>`** vía `@stack('jsonld')`, no en `<head>`, porque `wire:navigate` conserva los scripts del head y acumularía bloques (hallazgo probado en ParkingParaCamiones con test que cuenta ocurrencias). Se replica el test.

## Tratamiento de estados

| Caso | Respuesta HTTP | robots | Sitemap | Contenido |
|---|---|---|---|---|
| Publicada | 200 | index | Sí | Ficha completa |
| Pausada / caducada | 200 para propietario y admin; **404** para el resto | — | No | Se acepta que Google vea 404 temporal; si se reactiva vuelve a indexarse. Alternativa (410) descartada: podría reactivarse. |
| Vendida (≤ `sold_visible_days`) | 200 | index | Sí (`lastmod` = `sold_at`) | Ficha con banner "Vendida", sin contacto. Genera confianza y contenido |
| Vendida (> `sold_visible_days`) | 200 | `noindex,follow` | No | Solo por URL directa; enlaces a similares |
| Archivada / eliminada | **410 Gone** | — | No | Página de marca "Esta publicación ya no está disponible" con buscador. 410 acelera la desindexación |
| Suspendida | 404 | — | No | |
| Borrador | 404 (propietario/admin: 200 con banner) | — | No | |
| Slug antiguo | 301 → slug actual | — | — | |

## Sitemap y robots

- `/sitemap.xml` generado por controlador propio (sin paquete), cacheado 1 h e invalidado por eventos de publicación (`LISTING_CACHE_KEYS` pattern de ParkingParaCamiones): home, explorar, estáticas, categorías y provincias con contenido, fichas visibles con `lastmod`. Un solo archivo hasta 50.000 URLs; índice de sitemaps cuando haga falta.
- `/robots.txt` servido por ruta (no archivo estático) para que `Sitemap:` use `config('app.url')`: `Disallow: /panel /admin /login /register /settings /publicaciones/*/reportar`.
- Páginas con filtros (`?q=`, `?precio_min=`) llevan `canonical` a la URL limpia; `sector`/`provincia` en query string redirigen 301 a su URL propia.

## Breadcrumbs

`flux:breadcrumbs` visibles + `BreadcrumbList`. Inicio › Empresas › {Sector} › {Título}. Para provincia: Inicio › Empresas › {Provincia}.

## Rendimiento (Core Web Vitals)

- Imágenes con `width`/`height`, `loading="lazy"` salvo la portada de la ficha (`fetchpriority="high"`), conversiones a tamaños de tarjeta/ficha, WebP.
- Sin JS en la parte pública salvo Livewire/Alpine/Flux y el módulo de mapa (cargado solo en páginas con mapa vía `@vite` de entrada separada).
- Fuentes con `preconnect` y `font-display: swap`.
- Cache de consultas de agregados (recuentos por provincia/categoría) 15 min.

## Tests previstos

- Ficha publicada: title, description, canonical, og:image, un solo bloque JSON-LD en body.
- JSON-LD nunca contiene `latitude`/`longitude` ni dirección salvo `exact`.
- Sitemap incluye publicadas y vendidas recientes; excluye pausadas, categorías vacías.
- Categoría vacía → `noindex`.
- Slug antiguo → 301; archivada → 410; pausada → 404 anónimo / 200 propietario.
- robots.txt contiene `Sitemap:` con `app.url`.
