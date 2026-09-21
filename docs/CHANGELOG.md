# AVYTRA — Changelog

Formato: una sección por fase cerrada, con fecha. Cambios de documentación relevantes también se anotan.

## [Phase 0] — 2026-09-21 — Documentación y arquitectura

### Añadido
- Documentación modular en `docs/` (00–22): visión, alcance, roles, dominio, campos, ciclo de vida, base de datos, páginas públicas, panel/admin, ubicación y mapas, negocios online, contacto, vigencia, diseño, SEO, seguridad, medios, tests, arquitectura, fases, roadmap, referencia ParkingParaCamiones.
- `docs/DECISIONS.md` con ADR-001 a ADR-014.
- `docs/STATUS.md` y `docs/CHANGELOG.md`.
- `CLAUDE.md` con las instrucciones permanentes del proyecto (sección AVYTRA antes del bloque de Laravel Boost).

### Cambiado
- `.gitignore`: se retira `/CLAUDE.md` para versionar las instrucciones del proyecto.

### Sin cambios de código
- No hay migraciones, modelos, componentes ni dependencias nuevas.
