# AVYTRA — Changelog

Formato: una sección por fase cerrada, con fecha. Cambios de documentación relevantes también se anotan.

## [Phase 1] — 2026-09-21 — Fundamentos del proyecto

### Añadido
- `config/avytra.php` (vigencia, contacto, reportes, ubicación, mapa, geocodificación, soporte, límites) y variables `AVYTRA_*` en `.env.example`.
- Enum `UserRole`; migración `users.role`, `users.phone`, `users.is_assisted`; `User::isSuperadmin()`; estados de factory `superadmin()` y `assisted()`.
- Comando `avytra:superadmin {email} [--revoke]`.
- Middleware `EnsureUserIsSuperadmin` (alias `superadmin`, responde 404) y `routes/admin.php` con `/admin`.
- Tabla `audit_logs`, modelo `AuditLog`, factory y `App\Support\Audit\AuditLogger`.
- `App\Support\Seo\PageMeta` y layout público `layouts/public` con header, footer y metadatos.
- Componentes `x-app-logo` (wordmark) y `x-app-logo-icon` (símbolo) con los SVG de marca; favicon, apple-touch-icon, app icon y `og-default.png`.
- Traducciones `lang/es.json` y `lang/es/*.php`.
- Rate limiters `register` y `public`.
- Tests: acceso admin, comando superadmin, audit logger, configuración, home pública.
- README de arranque y `.claude/launch.json` para el servidor de previsualización.

### Cambiado
- Locale `es`, zona horaria `Europe/Madrid`, Fortify `home` → `/panel`; `/dashboard` pasa a `/panel` manteniendo el nombre `dashboard`.
- `app.css`: tokens de marca, Lato, accent de Flux en Lime/Ink. Vite carga Lato autoalojada en lugar de Instrument Sans.
- Layouts `app` (prop `area`, sidebar Ink en administración, sin enlaces del starter kit), `auth` (claros, logo real) y `partials/head` (modo claro por defecto).
- Página de apariencia sin opción "Sistema".
- `SecurityTest` usa `__()` para las cadenas.
- `composer types:check` con `--memory-limit=1G`; conexión mysql con `engine = InnoDB`.
- `Model::preventLazyLoading` activo fuera de producción.

### Eliminado
- `welcome.blade.php`, `placeholder-pattern.blade.php`, enlaces a repositorio/documentación del starter kit, `lang/en` publicado.

## [Phase 0] — 2026-09-21 — Documentación y arquitectura

### Añadido
- Documentación modular en `docs/` (00–22): visión, alcance, roles, dominio, campos, ciclo de vida, base de datos, páginas públicas, panel/admin, ubicación y mapas, negocios online, contacto, vigencia, diseño, SEO, seguridad, medios, tests, arquitectura, fases, roadmap, referencia ParkingParaCamiones.
- `docs/DECISIONS.md` con ADR-001 a ADR-014.
- `docs/STATUS.md` y `docs/CHANGELOG.md`.
- `CLAUDE.md` con las instrucciones permanentes del proyecto (sección AVYTRA antes del bloque de Laravel Boost).

### Cambiado
- `.gitignore`: se retira `/CLAUDE.md` para versionar las instrucciones del proyecto.
- Tras la revisión del propietario: varios tipos de operación por publicación (`listing_operation_types`, `primary_operation_type`, `stake_percent`, `operation_notes`, nuevo valor `partial_sale`) — ADR-015; propiedad directa por usuario sin equipos — ADR-016; confirmación de vigencia con login en lugar de enlace sin sesión — ADR-007 revisado. Documentos 04, 05, 06, 07, 08, 09, 13, 16, 18, 19, 20 y 21 actualizados.

### Sin cambios de código
- No hay migraciones, modelos, componentes ni dependencias nuevas.
