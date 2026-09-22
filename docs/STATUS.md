# AVYTRA — Project Status

## Current phase

**Phase 4 — Marketplace público: implementada, pendiente de revisión visual del propietario** (última tarea de la Definition of Done). Al aprobarla, comienza Phase 5 — Ubicación y mapas (requiere aprobar `maplibre-gl`).

Phase 3 se dio por aprobada el 2026-09-22 al pedir el propietario el inicio de Phase 4.

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
- `App\Support\Listings\PublicListingPresenter`: única proyección pública (ubicación por visibilidad, precio y métricas por divulgación, web/redes y forma jurídica por sus flags, canales sensibles solo tras clic, `pageMeta()` con JSON-LD `Offer` + `BreadcrumbList`). `PriceFormatter` y `MarketplaceAggregates` (recuentos cacheados por sector/provincia).
- Home real (`HomeController`): buscador texto + provincia, accesos rápidos calculados, últimas publicaciones, cómo funciona, confianza, CTA final, footer con sectores/provincias con publicaciones y legales. Header con Explorar / Negocios online / Vende tu empresa.
- Explorar `pages::public.listings.index` (filtros en URL, 24 por página, orden, recuento, estado vacío, flyout móvil) reutilizado por `/empresas/categoria/{slug}`, `/empresas/provincia/{slug}` y `/negocios-online` (noindex si vacías, canonical).
- Ficha `ListingController@show`: 200/404/410/301, banner para propietario/admin, vendida sin contacto, vendida antigua `noindex`, relacionadas, barra móvil "Contactar", lightbox Alpine preparado. Islas Livewire `public.contact-box` (revelación con rate limit) y `public.report-listing` (modal).
- Reportes: tabla `listing_reports`, modelo/factory/policy/enums, `SubmitListingReport`, `ResolveListingReport`, notificación `ListingReportReceived`, bandeja `pages::admin.reports.index` con acción rápida y audit.
- Componentes `x-listing-card`, `x-freshness-badge`, `x-price` (ahora presentacional), `x-public.legal-page`; páginas `/publicar`, `/como-funciona`, `/aviso-legal`, `/privacidad`, `/cookies` (texto provisional marcado); errores públicos `404` y `410`.
- La vista previa del paso 8 del wizard usa el parcial público real.
- Config: `avytra.pagination.public_cards`, `avytra.public.*`, `avytra.reports.{min_seconds_to_submit, message_max_length}`; limitadores `contact-reveal` y `report`; `throttle:public` en las rutas públicas.
- 201 cadenas nuevas en `lang/es.json`.

## In progress

- Nada.

## Next

- Revisión visual del propietario (requiere `npm run build` o `composer run dev`): home, `/empresas` con filtros (escritorio y flyout móvil), ficha de la publicación de demo (`/empresas/venta-de-tienda-online-de-consumibles-de-impresoras-en-borriana`) con "Mostrar teléfono" y "Reportar", `/negocios-online`, una categoría con y sin publicaciones, `/publicar`, legales, 404/410, `/admin/reportes` (crear un reporte desde la ficha y resolverlo). Comprobado en esta sesión con el navegador integrado: home, explorar (filtro Livewire), ficha con revelación de contacto y versión móvil sin scroll horizontal. `/admin/reportes` solo se ha verificado con tests (no se inicia sesión desde el navegador automatizado).
- Phase 5 — Ubicación y mapas: aprobar `maplibre-gl`, componente `x-map.listing` sobre `PublicListingPresenter::publicPoint()`, picker en el wizard, `Geocoder`, mapa opcional en explorar.

## Blockers

- Ninguno. Aprobaciones pendientes en su fase: `maplibre-gl` (Phase 5), `spatie/laravel-medialibrary` (Phase 6).
- Notas aceptadas de Phase 4:
  - Las rutas del panel de publicaciones pasan a llamarse `panel.listings.index|create|edit`: `listings.index` y `listings.show` son las públicas según docs/08. Las de empresas del panel (`businesses.*`) no cambian.
  - `tipo` acepta `fisico`/`online`/`hibrido`; `operacion` usa los valores del enum (`transfer`, `full_sale`…). El filtro de precio excluye las publicaciones "a consultar"; al ordenar por precio van al final.
  - La ruta directa `/publicaciones/{slug}/reportar` no existe (solo modal). `robots.txt` sigue estático y sin `Disallow` hasta Phase 9, igual que el sitemap, `ItemList` y la redirección 301 de `?sector=`/`?provincia=`.
  - El store de caché `database` de Laravel 13 solo deserializa clases permitidas: `MarketplaceAggregates` cachea arrays planos, nunca modelos ni colecciones.
  - El componente de explorar fija el layout con `$view->layout('layouts::public', [...])` en `rendering()`: el compilador de componentes single-file no admite `#[Layout]` delante de `new class`.
  - El `throttle:public` (60/min por IP) cubre las rutas GET públicas; las peticiones Livewire (`/livewire/update`) no pasan por él.
  - Notas de Phase 3 que siguen vigentes: `title`/`slug` nullables hasta publicar; `ExpireListing` no notifica (Phase 7); el limitador `register` se aplica en Phase 10.

## Important decisions

Ver `docs/DECISIONS.md` (ADR-001…017). Decisiones menores de Phase 4 en las notas de "Blockers".

## Last tests executed

- 2026-09-22 — `composer test` (Pint + Larastan nivel 7 + Pest): **393 tests, todo en verde** (332 de Phase 3 más 61 nuevos). Nuevos: `Support/PublicListingPresenterTest`, `Public/{ListingShow,ListingExplore,StaticPages}Test`, `Reports/ListingReportTest`, `Policies/ListingReportPolicyTest`; ampliado `Public/HomeTest`.

## Last updated

2026-09-22 — Phase 4 implementada y verificada con tests.
