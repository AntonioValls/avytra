# AVYTRA — Project Status

## Current phase

**Phase 2 — Dominio de empresas: implementada, pendiente de revisión visual del propietario** (última tarea de la Definition of Done). Al aprobarla, comienza Phase 3 — Publicaciones.

Phase 1 se dio por aprobada el 2026-09-22 al pedir el propietario el inicio de Phase 2.

## Completed

### Phase 0 — Documentación y arquitectura (2026-09-21)
- Documentos `docs/00` a `docs/22`, `DECISIONS.md` (ADR-001…016), `CHANGELOG.md`, `CLAUDE.md`. Análisis de ParkingParaCamiones.

### Phase 1 — Fundamentos (2026-09-21)
- Configuración, marca, layouts (`public`, `app` con área admin, `auth`), traducción, roles y comando `avytra:superadmin`, middleware `superadmin` (404), panel `/panel`, `/admin`, tabla `audit_logs` + `AuditLogger`, rate limiters `register` y `public`. Detalle en `CHANGELOG.md`.

### Phase 2 — Dominio de empresas (2026-09-22)
- Modelo: `Category`, `Region`, `Province`, `Municipality`, `Business` (soft deletes, `TracksAuthorship`), `Location`, `OnlineProfile`; once enums; factories con estados.
- Catálogo geográfico real (19 comunidades, 52 provincias, 8.131 municipios con centroide y población) en `database/data/spain/` con fuentes documentadas; comando `avytra:import-geography` idempotente. `CategorySeeder` con 16 sectores y 80 subsectores.
- `BusinessPolicy` (matriz completa con tests) y Actions `CreateBusiness`, `UpdateBusiness`, `TransferBusinessOwnership`, `SaveBusinessLocation`, `DeleteUserAccount`. Auditoría cuando el superadmin crea, edita o transfiere empresas ajenas.
- `PublicPointDeriver`: coordenadas públicas por visibilidad (exacta, aproximada con desplazamiento determinista 250–600 m dentro de 700 m, solo municipio con radio por población, oculta). Solo `SaveBusinessLocation` escribe `public_*`.
- Panel: `/panel` (Livewire, tarjetas de empresas o estado vacío), `/panel/empresas` (tarjetas, paginación, estado vacío), formulario crear/editar con secciones condicionadas por tipo (ubicación con provincia/municipio/dirección privada/visibilidad; perfil online con acordeón "más datos").
- Admin: `/admin/empresas` (tabla, filtros por texto/tipo/sector en la URL, crear con selector de propietario, editar, cambiar propietario con modal y audit).
- ADR-017: eliminar la cuenta borra las empresas del usuario (Action `DeleteUserAccount`; FK `restrictOnDelete` como red de seguridad).

## In progress

- Nada.

## Next

- Revisión visual del propietario: `/panel`, `/panel/empresas` (vacío y con datos), formulario en los tres tipos de negocio (móvil y escritorio), `/admin/empresas` con filtros y modal de cambio de propietario. Requiere `npm run build` (o `composer run dev`) para que Tailwind incluya las clases nuevas. Usuario local: `test@example.com` (superadmin restaurado tras `migrate:fresh --seed`).
- Phase 3 — Publicaciones: `listings` y tablas asociadas, `ListingStatus` con transiciones, `ListingPolicy`, Actions de transición, wizard de 8 pasos, `pages::listings.index`, avisos en el panel, admin de publicaciones. Al llegar: restringir `BusinessPolicy::delete` (sin publicaciones publicadas/vendidas) y ampliar `DeleteUserAccount` para archivar y borrar publicaciones.

## Blockers

- Ninguno. Aprobaciones pendientes en su fase: `maplibre-gl` (Phase 5), `spatie/laravel-medialibrary` (Phase 6).
- Notas aceptadas de Phase 2:
  - El formulario no ofrece latitud/longitud hasta el picker de mapa (Phase 5); el Action ya las acepta y deriva. Mientras tanto toda ubicación se publica como "solo municipio" (`Location::effectiveVisibility()`), y la UI lo explica con un aviso.
  - Municipio con `flux:select variant="listbox" searchable` en lugar de `flux:autocomplete` (se elige por id; documentado en `docs/10`).
  - `BusinessPolicy::delete` existe y está testeada, pero no hay botón de eliminar empresa hasta que Phase 3 aporte la condición sobre publicaciones.
  - Las tarjetas de "Mis empresas" muestran "Sin publicaciones todavía"; las acciones "Nueva publicación" y "Ver publicación activa" llegan en Phase 3.
  - El limitador `register` sigue pendiente de aplicarse (Phase 10).

## Important decisions

Ver `docs/DECISIONS.md` (ADR-001…017). Decisiones menores de Phase 2: el componente `pages::businesses.form` se comparte entre panel y admin recibiendo `admin=true` como valor por defecto de la ruta; el layout `app` deduce el área del nombre de la ruta; los radios del círculo "solo municipio" salen de `config/avytra.php` por tramos de población.

## Last tests executed

- 2026-09-22 — `composer test` (Pint + Larastan nivel 7 + Pest): **141 tests, 716 aserciones, todo en verde.** Nuevos: `Unit/Support/Location/PublicPointDeriverTest`, `Policies/BusinessPolicyTest`, `Concerns/TracksAuthorshipTest`, `Actions/Businesses/{CreateBusiness,UpdateBusiness,TransferBusinessOwnership}Test`, `Actions/Locations/SaveBusinessLocationTest`, `Actions/Users/DeleteUserAccountTest`, `Console/ImportSpanishGeographyTest`, `Seeders/CategorySeederTest`, `Businesses/{BusinessForm,BusinessIndex}Test`, `Admin/AdminBusinessesTest`; `Config/AvytraConfigTest` ampliado.

## Last updated

2026-09-22 — Phase 2 implementada y verificada con tests.
