# 08 — Área pública: páginas y flujos

Sin registro para consultar. Toda la parte pública se sirve con un layout propio (`layouts/public`) distinto del panel y de la administración.

## Mapa de rutas públicas

| Ruta | Nombre | Contenido |
|---|---|---|
| `/` | `home` | Home |
| `/empresas` | `listings.index` | Explorar con filtros (query string) |
| `/empresas/{slug}` | `listings.show` | Ficha de publicación |
| `/empresas/categoria/{category:slug}` | `categories.show` | Explorar filtrado por sector, con texto introductorio propio |
| `/empresas/provincia/{province:slug}` | `provinces.show` | Explorar filtrado por provincia (solo indexable si tiene publicaciones; ver SEO) |
| `/negocios-online` | `listings.online` | Alias de explorar con `type=online` y texto propio |
| `/publicar` | `publish.landing` | Landing "Vende tu empresa": explica el proceso y lleva a registro/wizard |
| `/como-funciona` | `how-it-works` | Explicación breve (opcional en MVP; contenido estático) |
| `/aviso-legal`, `/privacidad`, `/cookies` | `legal.*` | Estáticas |
| `/publicaciones/{slug}/reportar` | `listings.report` | Se abre como modal desde la ficha; ruta directa opcional |
| `/sitemap.xml`, `/robots.txt` | — | Ver SEO |

Rutas de auth (Fortify): `/login`, `/register`, `/forgot-password`, etc. (existentes).

## Home

Objetivo: comunicar en dos segundos qué es AVYTRA y llevar a explorar o publicar.

Secciones (de arriba abajo):

1. **Hero.** Titular "Encuentra un negocio que ya está en marcha." Subtítulo: "Empresas y negocios en venta o traspaso, con datos claros y disponibilidad confirmada." Buscador simple (texto + provincia opcional) → `/empresas`. CTA secundaria "Publicar empresa" (Lime).
2. **Accesos rápidos.** Chips: sectores principales, "Negocios online", "Traspasos", provincias con más publicaciones (calculado, cacheado).
3. **Últimas publicaciones.** 6–8 tarjetas de publicaciones publicadas más recientes.
4. **Cómo funciona.** Tres pasos para comprador y tres para vendedor. Flechas de continuidad (lenguaje gráfico de marca).
5. **Confianza.** Bloque explicando "Disponibilidad confirmada": las publicaciones sin confirmar se pausan automáticamente.
6. **CTA final.** "¿Y si tu próxima empresa ya existe?" / "Tu negocio puede tener una siguiente etapa."
7. Footer con enlaces a categorías, provincias con publicaciones, legales.

Sin carruseles automáticos, sin contadores animados, sin testimonios inventados.

## Explorar empresas (`/empresas`)

Componente Livewire de página con filtros en la URL (`#[Url]`) para que sean compartibles e indexables cuando proceda.

### Filtros esenciales del MVP

| Filtro | Control Flux | Parámetro |
|---|---|---|
| Texto | `flux:input` con icono, `wire:model.live.debounce.400ms` | `q` |
| Sector | `flux:select` (variant listbox) | `sector` |
| Tipo de negocio | `flux:radio.group` segmentado o `flux:select` | `tipo` (`fisico`, `online`, `hibrido`) |
| Tipo de operación | `flux:select` | `operacion` (coincide si la publicación ofrece ese tipo entre los suyos, vía `listing_operation_types`) |
| Provincia | `flux:select` con búsqueda (variant listbox `searchable`) | `provincia` |
| Precio | dos `flux:input type=number` (mín/máx) o `flux:slider` Pro | `precio_min`, `precio_max` |
| Orden | `flux:select` | `orden` (`recientes` por defecto, `precio_asc`, `precio_desc`, `confirmadas` = última confirmación) |

Fuera del MVP: facturación, antigüedad, negociable, radio geográfico, subsector como filtro (sí como chip visual).

### Comportamiento

- Móvil: filtros en un `flux:modal` (variant `flyout`) abierto con botón "Filtros (3)"; escritorio: barra lateral izquierda fija.
- Resultados: grid de tarjetas 1/2/3 columnas. Paginación `flux:pagination` (24 por página). **Siempre paginado.**
- Estado vacío: mensaje claro + botón "Quitar filtros" + CTA "¿Tienes un negocio? Publícalo gratis".
- Query: `Listing::publiclyVisible()` con `whereHas('business', ...)` para sector/tipo y `whereHas('business.location', ...)` para provincia; eager loading `business.category`, `business.location.province`, `business.media`. Búsqueda de texto con `LIKE` sobre título y nombre; FULLTEXT cuando haga falta.
- Se muestra el número total de resultados ("42 empresas").

### Vista de mapa

Fuera del MVP como pestaña completa. Phase 5 añade un mapa opcional en explorar (toggle "Ver mapa") que muestra las publicaciones **con ubicación pública** de la página actual, con círculos para aproximadas. Ver [10-location-and-maps.md](10-location-and-maps.md).

Implementación (Phase 5): botón "Ver mapa / Ocultar mapa" junto al orden (solo con resultados), estado `showMap` en la URL como `?mapa=true`, componente `x-map.explore` sobre la rejilla con los `mapPoint()` de las tarjetas de la página; el mapa se reconstruye con cada cambio de página o filtro. Si ninguna publicación de la página tiene ubicación pública, el bloque lo dice en texto.

Implementación (Phase 4): un único componente `pages::public.listings.index` sirve `/empresas`, `/empresas/categoria/{slug}`, `/empresas/provincia/{slug}` y `/negocios-online`; la ruta fija un filtro (`category`, `province` u `online=true` como valor por defecto) y el componente ajusta título, introducción, canonical y `noindex` cuando la página fija está vacía. Parámetros de URL: `q`, `sector` (slug), `tipo` (`fisico`/`online`/`hibrido`), `operacion` (valor del enum `OperationType`), `provincia` (slug), `precio_min`, `precio_max`, `orden` (`recientes`, `confirmadas`, `precio_asc`, `precio_desc`). El filtro de precio compara con el precio exacto o con el extremo del rango que puede satisfacerlo y deja fuera las publicaciones "a consultar"; al ordenar por precio, las que lo tienen van primero. Los recuentos por sector/provincia del footer y la home salen de `App\Support\Listings\MarketplaceAggregates` (caché de arrays planos, 15 min).

## Tarjeta de publicación

Componente Blade puro (`<x-listing-card :listing="$listing" />`), no Livewire. Datos:

- Portada (o placeholder con símbolo AVYTRA) 16:10, `loading="lazy"`, `width`/`height` explícitos.
- Badge de la operación principal (Traspaso, Venta, Socio…) más "+1"/"+2" si la publicación ofrece varias; badge de tipo de negocio si es online/híbrido.
- Título de la publicación (máx. 2 líneas).
- Sector · Ubicación pública ("Castellón de la Plana, Castellón", "Provincia de Castellón" o "Online").
- Precio ("485.000 €", "180.000–220.000 €", "Consultar"), con "Negociable" si procede.
- Hasta tres atributos pequeños: facturación si pública, empleados, año de inicio.
- Pie: "Confirmada hace 8 días" (icono check). Si `sold`: badge "Vendida".

Toda la tarjeta es un enlace con `wire:navigate`.

Implementación (Phase 4): `x-listing-card` recibe el `PublicListingPresenter`, nunca el modelo. Portada con placeholder de marca cuando la empresa no tiene imágenes. Desde Phase 6 la tarjeta pinta `coverImage()` del presentador (conversiones `thumb`/`card` en `srcset`, `width`/`height` fijos y `loading="lazy"`, salvo `eager`). `x-price` y `x-freshness-badge` son puramente presentacionales (texto ya formateado por `PriceFormatter`/el presentador).

## Ficha de publicación (`/empresas/{slug}`)

Controlador clásico + vista Blade (no necesita Livewire salvo para el modal de reporte y el botón "Mostrar teléfono"). Solo accesible si `isPubliclyVisible()`; en otro caso: propietario y superadmin ven la ficha con banner "Esta publicación no es pública (Borrador/Pausada/...)", el resto recibe 404 (o 410 para archivadas, ver SEO).

Estructura:

1. **Cabecera:** breadcrumbs (Inicio › Empresas › Sector › Título), badges (operación, tipo, "Vendida" si procede), título, línea de ubicación pública + sector, "Disponibilidad confirmada hace N días".
2. **Galería:** portada grande + miniaturas; lightbox con Alpine (mismo patrón que ParkingParaCamiones, sin librería externa). Sin imágenes: placeholder de marca.
3. **Columna principal:**
   - Resumen de datos clave en tarjetas: precio, facturación (si pública), beneficio (si pública), empleados, año de inicio, local (propiedad/alquiler).
   - Descripción (párrafos).
   - Puntos destacados.
   - Motivo de la venta.
   - Qué se incluye (lista con iconos check/cross, solo lo indicado).
   - Información económica pública (tabla; "Consultar" para `on_request`; las `hidden` no aparecen).
   - Negocio online (si procede): tipo, plataforma, métricas públicas, canales.
   - Ubicación: mapa según visibilidad + texto ("Zona aproximada en Castellón de la Plana"). Sin mapa si `hidden`.
4. **Columna lateral (sticky en escritorio, al final en móvil con barra fija inferior "Contactar"):**
   - **Contactar con el propietario**: nombre de contacto, método preferido destacado, resto de canales. Teléfono/WhatsApp tras clic "Mostrar" (Livewire, rate limit) para dificultar scraping.
   - Datos de la empresa: nombre comercial, sector, tipo, forma jurídica si visible, web si visible.
   - "Publicada el …" y "Disponibilidad confirmada hace N días".
   - Enlace "Reportar esta publicación" (modal).
   - Compartir (enlace copiar; sin SDKs sociales).
5. **Relacionadas:** 3–4 publicaciones del mismo sector o provincia.
6. JSON-LD y metadatos según [15-seo.md](15-seo.md).

### Ficha de publicación vendida

Mismo layout con banner "Esta empresa ya ha cambiado de manos" y sin bloque de contacto. Se mantiene pública `sold_visible_days` (configurable, 30 por defecto) para transmitir que la plataforma funciona; después, `noindex` y fuera de listados.

Implementación (Phase 4): `ListingController@show` resuelve el slug (con `listing_slug_redirects` → 301), responde 410 a archivadas y borradas, 404 a no públicas (200 con banner para propietario y superadmin) y 200 con `noindex` a vendidas antiguas. La vista recibe solo el presentador, el parcial `public/listings/partials/content` (compartido con la vista previa del paso 8 del wizard) y dos islas Livewire: `public.contact-box` (revelación con `RateLimiter` `contact-reveal`, valores nunca en el estado del componente) y `public.report-listing`. El mapa de la ubicación (`x-map.listing`, Phase 5) se renderiza dentro del parcial según la visibilidad pública, también en la vista previa del wizard; Desde Phase 6 la galería es real: `galleryImages()` (portada primero, luego la galería en su orden) alimenta la portada grande (`detail`, `fetchpriority="high"`), las miniaturas (`thumb`) y el lightbox Alpine (flechas y teclado); el logo aparece en la tarjeta "Sobre la empresa". Solo llegan al HTML URLs de conversiones WebP, nunca el original. Barra inferior fija "Contactar" en móvil.

## Reportar publicación

Modal Flux desde la ficha. Campos: motivo (`flux:radio.group`), mensaje opcional, email opcional (obligatorio si no hay sesión, para poder responder; no se publica). Honeypot + rate limit por IP (3 por hora) + máximo un reporte abierto por listing y `ip_hash`. Confirmación con `flux:toast`. El superadmin lo ve en `/admin/reportes`.

Implementación (Phase 4): tabla `listing_reports`, enums `ListingReportReason` y `ListingReportStatus`, Actions `SubmitListingReport` y `ResolveListingReport`, notificación `ListingReportReceived` a todos los superadmins, `ListingReportPolicy`. Honeypot + tiempo mínimo (`avytra.reports.min_seconds_to_submit`) + limitador `report` + un reporte abierto por publicación y visitante (usuario o hash de IP con la clave de la app). La ruta directa `/publicaciones/{slug}/reportar` no se ha creado: el modal cubre el caso y evita una página indexable.

## Landing "Publicar" (`/publicar`)

Explica: gratis, pasos del wizard, privacidad (ubicación aproximada, cifras ocultables), confirmación de disponibilidad. CTA → registro (o wizard si ya hay sesión). Incluye "¿Prefieres que lo hagamos por ti?" con teléfono/email de AVYTRA (dato de config, no de un usuario), para el público poco tecnológico.

Implementación (Phase 4): `public/publish`, `public/how-it-works` y las tres legales (`public/legal/*` sobre el componente `x-public.legal-page`, con aviso de texto provisional). El CTA lleva al registro o al wizard según haya sesión; el teléfono y el email de soporte salen de `config('avytra.support')`.

## Estados y errores

- 404 con layout público y buscador.
- 410 para publicaciones archivadas/eliminadas que tuvieron URL pública.
- Mensaje de mantenimiento con marca.
- Todas las páginas responsive; navegación móvil con `flux:sidebar` colapsable del layout público o `flux:navbar` + menú.
