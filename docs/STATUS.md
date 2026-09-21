# AVYTRA — Project Status

## Current phase

**Phase 1 — Fundamentos del proyecto: implementada, pendiente de revisión visual del propietario** (última tarea de la Definition of Done). Al aprobarla, comienza Phase 2 — Dominio de empresas.

## Completed

### Phase 0 — Documentación y arquitectura (2026-09-21)
- Documentos `docs/00` a `docs/22`, `DECISIONS.md` (ADR-001…016), `CHANGELOG.md`, `CLAUDE.md`. Análisis de ParkingParaCamiones.

### Phase 1 — Fundamentos (2026-09-21)
- Configuración: `es`/`es_ES`, `Europe/Madrid`, `.env.example` corregido (mysql/avytra), `config/avytra.php` con todas las claves de producto, Fortify `home` → `/panel`.
- Marca: Lato autoalojada vía el plugin de fuentes de Vite, tokens Ink/Lime/Transfer/Mist/Slate y radios en `app.css`, accent de Flux = Lime con texto Ink, logotipo y símbolo como componentes Blade con `currentColor`, favicon SVG/ICO, apple-touch-icon y `og-default.png` generados desde el SVG de marca. Modo claro por defecto (script previo a `@fluxAppearance`; la página de apariencia ofrece solo claro/oscuro).
- Layouts: `layouts/public` (header, footer, metadatos vía `App\Support\Seo\PageMeta`, JSON-LD en body, siempre claro), `layouts/app` con prop `area` (`app` | `admin`, sidebar Ink en administración), layouts de auth rebrandeados; eliminados `welcome` y enlaces del starter kit.
- Traducción: `lang/es.json` (UI y emails de Fortify/Laravel) y `lang/es/{auth,pagination,passwords,validation}.php`. Vistas existentes en español.
- Roles: `users.role` (+ `phone`, `is_assisted`), enum `UserRole`, `User::isSuperadmin()`, comando `avytra:superadmin {email} [--revoke]`, middleware `superadmin` (404), `routes/admin.php` con `/admin`.
- Panel en `/panel` (nombre de ruta `dashboard`) con estado vacío; `/admin` con resumen operativo vacío.
- Auditoría base: tabla `audit_logs`, modelo `AuditLog` (+ factory) y `App\Support\Audit\AuditLogger`.
- Rate limiters nombrados `register` y `public` (definidos; se aplican a rutas en las fases que las crean).
- `Model::preventLazyLoading` fuera de producción. README de arranque. `composer types:check` con `--memory-limit=1G`. `config/database.php` mysql con `engine = InnoDB`.

## In progress

- Nada.

## Next

- Revisión visual del propietario (home, login, panel, admin en móvil y escritorio). Capturas comprobadas en local con el usuario sembrado `test@example.com` (superadmin). Pendiente de comprobar en un navegador real: apertura del menú móvil (el navegador integrado de la herramienta cortaba la carga de `livewire.js`/`flux.js` de forma intermitente; `curl` y `fetch` devuelven los archivos completos, así que no es un problema de la aplicación).
- Phase 2 — Dominio de empresas: catálogo geográfico y de categorías, `Business`, `Location`, `OnlineProfile`, `BusinessPolicy`, trait `TracksAuthorship`, Actions, formularios Livewire, admin de empresas, decisión sobre eliminación de cuenta (ADR).

## Blockers

- Ninguno. Aprobaciones pendientes en su fase: `maplibre-gl` (Phase 5), `spatie/laravel-medialibrary` (Phase 6).
- Nota: el limitador `register` no puede aplicarse a la ruta de registro de Fortify por configuración; se aplicará con un middleware propio o honeypot en Phase 10.

## Important decisions

Ver `docs/DECISIONS.md` (ADR-001…016). Decisiones menores tomadas en Phase 1 y documentadas en `docs/14-ui-design-system.md`: sin opción "Sistema" de apariencia (el panel arranca en claro), fuentes autoalojadas por Vite en lugar de CDN, prop `area` (no `variant`) en el layout porque Blaze pliega componentes y `variant` colisionaba con los iconos de Flux.

## Last tests executed

- 2026-09-21 — `composer test` (Pint + Larastan nivel 7 + Pest): **49 tests, 124 aserciones, todo en verde.** Nuevos: `Admin/AdminAccessTest`, `Console/MakeSuperadminTest`, `Audit/AuditLoggerTest`, `Config/AvytraConfigTest`, `Public/HomeTest`; `Settings/SecurityTest` adaptado a `__()`.

## Last updated

2026-09-21 — Phase 1 implementada y verificada en local.
