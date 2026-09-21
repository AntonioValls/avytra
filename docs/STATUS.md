# AVYTRA — Project Status

## Current phase

**Phase 0 — Documentación y arquitectura.** Documentación completa redactada; pendiente de revisión y aprobación del propietario antes de iniciar Phase 1.

## Completed

- Inspección del repositorio: Laravel Livewire Starter Kit sin modificar (Laravel 13.32, Livewire 4.4.5, Flux/Flux Pro 2.20, Fortify 1.39 con 2FA y passkeys, Pest 5.2, Tailwind 4, Vite 8, PHP 8.4.15).
- Lectura del manual de marca (`docs/design-system/`), tokens y logos SVG.
- Análisis de ParkingParaCamiones (`docs/22-parkingparacamiones-reference.md`).
- Documentos `docs/00` a `docs/22`, `DECISIONS.md` (ADR-001…014), `CHANGELOG.md`, `CLAUDE.md`.

## In progress

- Nada. Esperando revisión de Phase 0.

## Next

- Phase 1 — Fundamentos: configuración (es / Europe/Madrid / `.env.example`), branding (Lato, tokens, logos, favicon, modo claro), layouts público/panel/admin, traducción de vistas existentes, `users.role` + `UserRole` + comando superadmin + middleware admin (404), `/dashboard` → `/panel`, `config/avytra.php`, `audit_logs` + `TracksAuthorship`, rate limiters, README.

## Blockers

- Ninguno técnico. Decisiones que requieren aprobación del propietario antes de su fase: `maplibre-gl` (Phase 5), `spatie/laravel-medialibrary` (Phase 6).
- `/CLAUDE.md` estaba en `.gitignore` (añadido por Laravel Boost). Se ha retirado de `.gitignore` en Phase 0 para versionar las instrucciones del proyecto; el bloque `<laravel-boost-guidelines>` sigue siendo regenerable por `boost:update`.

## Important decisions

Ver `docs/DECISIONS.md`. Resumen: Business ≠ Listing (ADR-001); roles por enum sin paquete (002); IDs autoincrementales + slugs (003); coordenadas privadas/públicas derivadas con `location_visibility` (004); MapLibre + OpenFreeMap configurable (005); Media Library propuesta (006); vigencia 45/55/60 configurable con confirmación autenticada de un botón (007, revisado); zona horaria Europe/Madrid (008); contacto en la publicación (009); métricas financieras por fila con divulgación (010); moderación posterior (011); auditoría propia (012); búsqueda SQL (013); claves de traducción en inglés (014); varios tipos de operación por publicación con operación principal (015); propiedad directa por usuario, sin equipos (016).

Dudas de Phase 0 resueltas por el propietario el 2026-09-21: (1) una publicación puede ofrecer varios tipos de operación; (2) asesores/brokers sin previsión clara → propiedad directa; (3) confirmación de vigencia con login.

## Last tests executed

- No se han ejecutado tests en Phase 0 (no hay cambios de código). Suite del starter kit intacta.

## Last updated

2026-09-21 — Phase 0 entregada para revisión.
