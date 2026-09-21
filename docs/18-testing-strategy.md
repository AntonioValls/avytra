# 18 — Estrategia de testing

## Herramientas (ya instaladas)

- **Pest 5** con `pest-plugin-laravel`; `RefreshDatabase` en `tests/Feature` (configurado en `tests/Pest.php`).
- SQLite en memoria (`phpunit.xml`). Consecuencia: no usar funciones SQL exclusivas de MySQL en código de producción sin fallback, o marcar esos tests para MySQL.
- **Larastan** nivel actual de `phpstan.neon`; **Pint**. `composer test` ejecuta lint + análisis estático + tests, igual que CI (`.github/workflows/tests.yml`).
- Factories para todos los modelos con estados semánticos: `Business::factory()->physical()->online()->hybrid()`, `Listing::factory()->draft()->published()->expired()->sold()->needingConfirmation()`, `Location::factory()->approximate()`, `User::factory()->superadmin()`.
- Helpers en `tests/Pest.php`: `actingAsSuperadmin()`, `actingAsOwnerOf($business)`.

## Qué se prueba (prioridad por reglas de negocio)

| Área | Tipo | Ejemplos |
|---|---|---|
| Policies y ownership | Feature (Gate) | Usuario no puede editar empresa ajena; superadmin sí; invitado no puede crear; propietario no puede suspender |
| Superadmin | Feature | Crea empresa para otro usuario: `owner` ≠ `created_by`; cambia propietario con audit log; acceso a `/admin` da 404 a usuarios normales |
| Creación de empresas | Feature (Livewire) | Crear física exige ubicación al publicar; online no admite ubicación; varias empresas por usuario |
| Estados y transiciones | Unit + Feature | Tabla completa de transiciones permitidas/prohibidas (dataset); efectos en timestamps; eventos registrados |
| Publicación | Feature (Livewire wizard) | Validación por paso; validación "listo para publicar"; borrador persiste entre pasos; título/slug sugeridos |
| Renovación y caducidad | Feature (comando + `travel`) | Avisos a día 45/55, pausa a 60, idempotencia, reset al confirmar, enlace firmado válido/caducado/manipulado |
| Visibilidad | Feature (HTTP) | Proyección pública no filtra datos privados por cada `LocationVisibility` y `Disclosure`; pausada = 404 anónimo / 200 propietario; vendida antigua = noindex; archivada = 410 |
| Contacto | Feature | Canal coherente con preferencia; teléfono solo tras revelar; rate limit |
| Filtros y búsqueda | Feature (Livewire) | Cada filtro esencial; paginación; orden; estado vacío |
| Rutas públicas y SEO | Feature (HTTP) | Home, explorar, ficha, categoría, provincia, sitemap, robots, 301 de slug, JSON-LD único en body |
| Acciones críticas Livewire | Feature | Pausar, reactivar, marcar vendida, archivar, confirmar desde panel; confirmaciones de modal |
| Mapas | Unit + Feature | Derivación de coordenadas públicas; contención en el radio; HTML del componente de mapa según visibilidad |
| Medios | Feature (`Storage::fake`) | Validación, colecciones, orden, borrado |
| Reportes | Feature | Creación anónima con email; rate limit; resolución por admin |
| Config | Unit | Invariante `first < second < period` |

No se busca cobertura artificial. Sin tests de vistas puramente estáticas, ni de getters triviales.

## Convenciones

- Nombres descriptivos en inglés, estilo Pest: `it('pauses a listing when the owner does not confirm within the period', ...)`.
- Un archivo por componente/feature: `tests/Feature/Listings/PublishListingTest.php`, `tests/Feature/Admin/...`, `tests/Feature/Public/...`, `tests/Unit/Enums/ListingStatusTest.php`.
- Datasets de Pest para tablas de transiciones y de visibilidad.
- `Notification::fake()`, `Queue::fake()`, `Storage::fake()`, `travelTo()` en lugar de esperas reales.
- Tests de Livewire con `Livewire::test(...)->set()->call()->assertHasErrors()` y `assertSee`/`assertDontSee` para visibilidad.
- Los tests que dependen de la zona horaria fijan `Europe/Madrid` (ya en config) y usan `travelTo` con instantes explícitos.
- Se lee la skill `testing-best-practices` antes de escribir tests (regla del proyecto).

## Definición de "tests relevantes pasando" por fase

Cada fase de [20-development-phases.md](20-development-phases.md) enumera sus tests esperados. Una fase no se cierra sin que `composer test` (Pint + Larastan + Pest) pase completo, y `STATUS.md` registra la fecha y el resultado.

## Fuera del MVP

- Tests de navegador (Dusk/Playwright) para el mapa y el lightbox: se prueban a nivel de HTML generado. Si el mapa causa regresiones, se evaluará Pest Browser en Phase 10.
- Tests de rendimiento.
