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
- Tras la revisión del propietario: varios tipos de operación por publicación (`listing_operation_types`, `primary_operation_type`, `stake_percent`, `operation_notes`, nuevo valor `partial_sale`) — ADR-015; propiedad directa por usuario sin equipos — ADR-016; confirmación de vigencia con login en lugar de enlace sin sesión — ADR-007 revisado. Documentos 04, 05, 06, 07, 08, 09, 13, 16, 18, 19, 20 y 21 actualizados.

### Sin cambios de código
- No hay migraciones, modelos, componentes ni dependencias nuevas.
