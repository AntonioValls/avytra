# 20 — Fases de desarrollo

Cada fase es pequeña, verificable y se ejecuta una por una. Ninguna fase empieza sin que la anterior cumpla la Definition of Done. Al cerrar cada fase se actualizan `STATUS.md` y `CHANGELOG.md`.

## Definition of Done (aplica a todas las fases)

- Funcionalidad implementada según los documentos de `/docs` correspondientes.
- Permisos revisados (Policies + tests).
- Validación revisada (por paso y "listo para publicar" donde aplique).
- UI responsive comprobada en móvil y escritorio.
- Estados vacíos y errores contemplados.
- Tests relevantes pasando: `composer test` completo (Pint + Larastan + Pest).
- Documentación actualizada si algo cambió (y ADR si contradice una decisión previa).
- `STATUS.md` y `CHANGELOG.md` actualizados.
- Sin TODO críticos ocultos (los TODO aceptados se listan en STATUS "Blockers/Next").

---

## Phase 0 — Documentación y arquitectura (actual)

**Objetivo:** fuente de verdad documental antes de escribir código.
**Alcance:** los 23 documentos de `/docs`, `CLAUDE.md`, `DECISIONS.md`, `STATUS.md`, `CHANGELOG.md`; análisis de ParkingParaCamiones.
**Dependencias:** ninguna.
**Criterios de aceptación:** revisión crítica (sección 45 de la especificación) respondida; propietario revisa y aprueba.
**Tests:** ninguno.
**Terminado cuando:** el propietario aprueba y se inicia Phase 1.

---

## Phase 1 — Fundamentos del proyecto

**Objetivo:** proyecto configurado, con marca, layouts, roles y estructura base, sin dominio todavía.
**Dependencias:** Phase 0 aprobada.

**Tareas**

1. Configuración: `APP_LOCALE=es`, `APP_FALLBACK_LOCALE=es`, `APP_FAKER_LOCALE=es_ES`, `timezone=Europe/Madrid`, corregir `.env.example` (`DB_CONNECTION=mysql`, `DB_DATABASE=avytra`), `APP_NAME=AVYTRA`, `MAIL_FROM_*`. Crear `config/avytra.php` con todas las claves (aunque aún no se usen todas). Quitar `/CLAUDE.md` de `.gitignore` para versionarlo (ver STATUS).
2. Branding: Lato en `partials/head`, tokens de color y radios en `app.css`, logos SVG en `resources/svg` y componentes `x-app-logo`/`x-app-logo-icon`, favicon/app icon en `public/`, modo claro por defecto (eliminar `class="dark"` fijo), eliminar enlaces del starter kit en el sidebar.
3. Layouts: `layouts/public` (header, footer, slot de meta vía `PageMeta`), `layouts/app` rebrandeado con variante admin, `layouts/auth` rebrandeado. Página home provisional con hero y CTA (contenido real llega en Phase 4).
4. Traducción al español de todas las vistas existentes (auth, settings, sidebar, emails de Fortify) mediante `lang/es.json`.
5. Roles: migración `users.role`, enum `UserRole`, `User::isSuperadmin()`, comando `avytra:superadmin {email}`, middleware `EnsureUserIsSuperadmin` (404), ruta `/admin` con página vacía "Administración".
6. Panel: ruta `/dashboard` → `/panel` (nombre `dashboard`), página inicial con estado vacío.
7. Base de auditoría: tabla `audit_logs`, modelo, `AuditLogger` mínimo, trait `TracksAuthorship` (sin modelos que lo usen aún).
8. Rate limiters nombrados en `AppServiceProvider` (registro, público).
9. README con instrucciones de arranque (`composer setup`, `composer run dev`).

**Criterios de aceptación:** app arranca con marca AVYTRA en español, modo claro; login/registro funcionan; usuario normal no ve `/admin` (404); superadmin creado por comando accede; `composer test` pasa.
**Tests esperados:** existentes adaptados al español; `UserRole`; comando superadmin; middleware admin (404/200); `TracksAuthorship` con un modelo de prueba o diferido a Phase 2; invariante de config de vigencia.
**Terminado cuando:** DoD + captura de home/login/panel en móvil y escritorio revisadas por el propietario.

---

## Phase 2 — Dominio de empresas

**Objetivo:** un usuario crea y edita sus empresas; el superadmin crea empresas para cualquiera.
**Dependencias:** Phase 1.

**Tareas**

1. Migraciones y modelos: `categories`, `regions`, `provinces`, `municipalities`, `businesses`, `locations`, `online_profiles`. Enums asociados. Factories con estados. Seeders: categorías (contenido real), geografía de España desde `database/data/spain/` (fuente documentada) mediante comando `avytra:import-geography`.
2. `BusinessPolicy` + tests completos de la matriz.
3. Actions: `CreateBusiness`, `UpdateBusiness`, `SaveBusinessLocation` (incluye `PublicPointDeriver` con jitter determinista y centroides), `TransferBusinessOwnership`.
4. Livewire: `pages::businesses.index` (tarjetas), `pages::businesses.form` (crear/editar) con Form Objects; secciones condicionadas por tipo; selects de provincia/municipio (`flux:select searchable`, `flux:autocomplete`); campos de ubicación **sin mapa todavía** (lat/lng se rellenan en Phase 5; en Phase 2 se acepta `city_only`/centroide).
5. Admin: `admin/businesses` (tabla, filtros, crear con selector de propietario, editar, cambiar propietario con audit).
6. Decidir y aplicar el efecto de eliminar cuenta sobre empresas (ADR).

**Criterios de aceptación:** un usuario crea varias empresas de los tres tipos; no puede ver/editar ajenas; superadmin crea para otro y queda `created_by` ≠ `owner`; ubicación derivada correcta por visibilidad; `composer test` pasa.
**Tests esperados:** Policy; crear/editar por tipo con validaciones de tipo; derivación de coordenadas públicas (unit, determinismo, contención); admin crea/transfiere con audit; seeders de geografía cargan recuentos esperados.
**Terminado cuando:** DoD.

---

## Phase 3 — Publicaciones

**Objetivo:** wizard completo, borradores, estados y publicación (sin parte pública todavía).
**Dependencias:** Phase 2.

**Tareas**

1. Migraciones y modelos: `listings`, `listing_operation_types`, `listing_financial_metrics`, `listing_events`, `listing_slug_redirects`. Enums `ListingStatus` (con tabla de transiciones), `OperationType`, `PriceDisclosure`, `Disclosure`, `FinancialMetric`, `ContactMethod`, `ListingEventType`. Factories.
2. `ListingPolicy` + tests de matriz.
3. Actions de transición (todos los de [06](06-listing-lifecycle.md)) + `CreateListingDraft` + `ChangeListingSlug` + validador "listo para publicar" (`ListingPublishabilityValidator` o reglas en `PublishListing`).
4. Wizard Livewire de 8 pasos con Form Objects, persistencia por paso, progreso, guardar y salir, modo edición, título sugerido, prefill de contacto visible. Paso 7 (imágenes) muestra placeholder "disponible en Phase 6" o se implementa con subida básica sin conversiones (decisión al llegar: preferible dejar el paso con aviso y no hacer trabajo desechable).
5. `pages::listings.index` (Mis publicaciones) con acciones por estado y modales de confirmación.
6. Panel inicio con avisos accionables (borradores, necesita confirmación — este último activo desde Phase 7 pero la UI ya lo contempla).
7. Notificación `ListingPublished`.
8. Admin: `admin/listings` (tabla, filtros por estado, acciones de transición incl. suspender con motivo, timeline de eventos).

**Criterios de aceptación:** el usuario publica una empresa sin ver más de un paso a la vez; puede abandonar y continuar; todas las transiciones válidas funcionan y las inválidas fallan con mensaje; superadmin gestiona publicaciones ajenas con audit; `composer test` pasa.
**Tests esperados:** Policy; dataset completo de transiciones; wizard paso a paso (validación por paso, persistencia, publicabilidad); slug único y regeneración al publicar; una publicación activa por empresa; acciones del panel; admin suspende/levanta.
**Terminado cuando:** DoD.

---

## Phase 4 — Marketplace público

**Objetivo:** home, explorar con filtros esenciales, tarjeta y ficha pública con proyección segura.
**Dependencias:** Phase 3.

**Tareas**

1. `PublicListingPresenter` (proyección con visibilidad) + test de "nunca filtra datos privados".
2. Home real (secciones de [08](08-public-pages-and-flows.md)).
3. `pages::public.listings.index` con filtros en URL, paginación, orden, estado vacío, flyout móvil.
4. `x-listing-card`, `x-price`, `x-freshness-badge`.
5. Ficha (`ListingController@show`) con todas las secciones excepto mapa (Phase 5) e imágenes reales (Phase 6, con placeholder); lightbox Alpine preparado. Bloque de contacto con revelación de teléfono/email (rate limit). Ficha vendida. 404/410 y vista de propietario con banner.
6. Páginas de categoría, provincia y `/negocios-online` (con `noindex` si vacías — la parte de metadatos completa llega en Phase 9, pero canonical/robots básicos se ponen aquí).
7. Reportar publicación (modal, tabla `listing_reports`, rate limit, honeypot, notificación al superadmin) y bandeja `admin/reports`.
8. Páginas estáticas (legales, cómo funciona, publicar) con textos provisionales marcados.

**Criterios de aceptación:** un visitante encuentra y contacta; nada privado aparece en HTML; filtros compartibles por URL; móvil cuidado; `composer test` pasa.
**Tests esperados:** rutas públicas; cada filtro; paginación; proyección por cada nivel de visibilidad/divulgación; revelación de contacto y rate limit; estados 404/410/200; reportes.
**Terminado cuando:** DoD + revisión visual del propietario.

---

## Phase 5 — Ubicación y mapas

**Objetivo:** mapa en ficha y selector de ubicación con pin en el wizard, con privacidad.
**Dependencias:** Phase 4. **Aprobación previa:** añadir `maplibre-gl`.

**Tareas**

1. Añadir `maplibre-gl`; módulo `resources/js/map/` (`map-support` con fallback WebGL, `mountListingMap`, `mountLocationPicker`); entrada Vite separada; verificar el problema del worker con Vite 8 y resolverlo sin archivos vendorizados a mano.
2. `config('avytra.map.style_url')`, atribución, colores desde tokens.
3. Componente `x-map.listing` con render por visibilidad (pin / círculo / nada) y fallback.
4. Picker en el paso 5 del wizard y en el formulario de empresa: centrado por municipio, pin arrastrable, guarda coordenadas privadas y recalcula públicas; visibilidad con explicación.
5. Interfaz `Geocoder` + `NullGeocoder` + `NominatimGeocoder` (dev) con rate limit y caché; botón "Buscar dirección" solo si driver ≠ null.
6. Mapa opcional en explorar (toggle) con los puntos públicos de la página actual.

**Criterios de aceptación:** ficha muestra mapa coherente con la visibilidad; dirección exacta jamás en HTML salvo `exact`; sin WebGL hay alternativa; `composer test` pasa.
**Tests esperados:** HTML del componente por visibilidad; `data-*` solo con `public_*`; picker persiste y deriva; geocoder null desactiva la UI; Nominatim respeta el rate limit (fake HTTP).
**Terminado cuando:** DoD.

---

## Phase 6 — Medios

**Objetivo:** logo, portada y galería con conversiones y orden.
**Dependencias:** Phase 4. **Aprobación previa:** `spatie/laravel-medialibrary` (o alternativa B).

**Tareas:** instalación y config del paquete; colecciones y conversiones; paso 7 del wizard y sección de imágenes del formulario de empresa con `flux:file-upload`, orden `wire:sort`, alt; tarjetas y ficha con `srcset`; OG image; placeholders; cola de conversiones; comando de regeneración documentado.
**Criterios de aceptación:** subir, ordenar, borrar; conversiones en cola; validación estricta; `composer test` pasa.
**Tests esperados:** los de [17](17-media-strategy.md).
**Terminado cuando:** DoD.

---

## Phase 7 — Sistema de vigencia

**Objetivo:** recordatorios, confirmación de un clic, pausa automática.
**Dependencias:** Phase 3 (puede hacerse en paralelo con 5/6 si conviene).

**Tareas:** comando `avytra:listings:process-freshness` (3 pasadas idempotentes, `--dry-run`); scheduler hourly; notificaciones `ListingFreshnessReminder` (dos etapas) y `ListingExpired` con plantillas de marca; página autenticada de confirmación enlazada desde el email (firma temporal + `auth` + Policy, botón único "Sí, sigue disponible"); botón "Sigue disponible" en panel; acción admin "confirmar en nombre de"; badges "Necesita confirmación"; texto público de frescura; listado admin de caducadas y de avisos fallidos.
**Criterios de aceptación:** con `travel`, la secuencia 45/55/60 ocurre exactamente una vez; confirmar reinicia; enlace caducado no funciona; nada se borra; `composer test` pasa.
**Tests esperados:** los de [13](13-freshness-and-notifications.md).
**Terminado cuando:** DoD.

---

## Phase 8 — Administración y asistencia

**Objetivo:** cerrar el círculo de asistencia y moderación.
**Dependencias:** Phases 3–7.

**Tareas:** `admin/users` (tabla, detalle, crear en nombre de otra persona con email opcional de establecer contraseña); resumen operativo en `/admin`; `admin/audit`; flujo completo "llamada de teléfono" probado end-to-end (crear usuario → empresa → publicación → publicar → confirmar en su nombre); `flux:command` global (opcional); resolución de reportes con acciones rápidas.
**Criterios de aceptación:** el superadmin completa el flujo de asistencia sin impersonar y con trazabilidad completa; `composer test` pasa.
**Tests esperados:** creación de usuario por admin; audit de cada acción; resumen operativo muestra recuentos correctos; 404 para no admin en todas las rutas.
**Terminado cuando:** DoD.

---

## Phase 9 — SEO

**Objetivo:** indexación correcta y metadatos completos.
**Dependencias:** Phases 4–6.

**Tareas:** `PageMeta` completo en todas las páginas públicas; JSON-LD (`Offer`, `ItemList`, `BreadcrumbList`, `WebSite`) en body; sitemap con caché e invalidación; robots por ruta; redirecciones de slug; tratamiento de vendidas/pausadas/archivadas; canonical de filtros; OG images; breadcrumbs; textos reales de categorías.
**Criterios de aceptación:** los tests de [15](15-seo.md) pasan; validación manual con herramientas de resultados enriquecidos.
**Terminado cuando:** DoD.

---

## Phase 10 — Endurecimiento y lanzamiento

**Objetivo:** listo para producción.
**Dependencias:** todas.

**Tareas:** revisión de seguridad ([16](16-security-and-privacy.md)) punto por punto; `composer audit`/`npm audit` en CI; rendimiento (N+1 con `Model::preventLazyLoading` en local/tests, índices, caché de agregados, Blaze en tarjetas); responsive final; accesibilidad (contraste, foco, labels, alt); textos legales definitivos; emails probados en clientes reales; configuración de producción (queue worker, cron, disco de medios, backups, logs); monitorización del scheduler; página de mantenimiento; documentación de despliegue; smoke test en staging.
**Criterios de aceptación:** checklist completo firmado en STATUS; `composer test` pasa; despliegue de prueba realizado.
**Terminado cuando:** lanzamiento.

---

## Fuera de fases (post-lanzamiento)

Ver [21-future-roadmap.md](21-future-roadmap.md). Cada mejora futura se convertirá en una fase numerada (11, 12…) con el mismo formato antes de empezar.
