# AVYTRA — Instrucciones permanentes del proyecto

Este archivo es la guía de trabajo de Claude (y de cualquier desarrollador) en AVYTRA. La documentación detallada vive en `docs/`. Si algo aquí contradice `docs/`, prevalece `docs/` y este archivo debe corregirse.

## Qué es AVYTRA

Plataforma web **gratuita** para descubrir, publicar, vender y traspasar empresas y negocios. Claim: "Empresas que cambian de manos." Línea de acción: "Compra. Vende. Continúa." Escaparate y punto de conexión: AVYTRA no intermedia, no cobra y no procesa pagos. Rasgo distintivo: **disponibilidad confirmada** (las publicaciones sin confirmar se pausan automáticamente, nunca se borran).

## Stack (no presuponer versiones; comprobar con `composer show --direct` y `package.json`)

PHP 8.4 · Laravel 13 · Livewire 4 (componentes single-file `⚡nombre.blade.php` + `Route::livewire()`) · Flux UI 2 + Flux UI Pro 2 · Tailwind CSS 4 · Vite 8 · Fortify (registro, reset, verificación, 2FA, passkeys) · Pest 5 · Larastan · Pint · MySQL/MariaDB (SQLite en memoria para tests) · Blaze.

**No añadir ni sustituir dependencias sin aprobación explícita del propietario** y sin registrar un ADR en `docs/DECISIONS.md`. Aprobados: `maplibre-gl` (Phase 5, ADR-005) y `spatie/laravel-medialibrary` (Phase 6, ADR-006).

## Metodología por fases (obligatoria)

1. El proyecto se ejecuta fase a fase según `docs/20-development-phases.md`. No se empieza una fase sin cerrar la anterior ni sin la aprobación del propietario.
2. **Antes de implementar cualquier funcionalidad, leer los documentos de `docs/` correspondientes.** Si una decisión nueva contradice la documentación: primero se actualiza la decisión en `docs/DECISIONS.md` (ADR) y el documento afectado, después se implementa.
3. Al cerrar una fase (y en cualquier sesión que deje trabajo a medias): actualizar `docs/STATUS.md` (fase actual, completado, en progreso, siguiente, bloqueos, últimos tests) y `docs/CHANGELOG.md`.
4. Definition of Done de una fase: funcionalidad implementada, permisos revisados, validación revisada, UI responsive, estados vacíos y errores contemplados, tests relevantes pasando (`composer test` = Pint + Larastan + Pest), documentación y STATUS actualizados, sin TODO críticos ocultos.
5. Comprobar tests antes de afirmar que algo funciona. Ejecutar el subconjunto afectado durante el desarrollo y la suite completa al cerrar.
6. **Inspeccionar el código existente antes de crear algo**: comprobar si Flux ya tiene el componente, si existe un Action/scope/componente similar, si el starter kit ya lo resuelve. No duplicar.

## Principios arquitectónicos

- **Business ≠ Listing.** `Business` es la empresa real (propiedad de un `User`); `Listing` es la publicación (operación, precio, contacto, estado, vigencia). Una empresa tiene histórico de publicaciones y como máximo una no terminada. Ver `docs/04-domain-model.md`.
- **Tres tipos de negocio** desde el principio: `physical` (con `Location`), `online` (con `OnlineProfile`), `hybrid` (ambos).
- **Policies son la única fuente de autorización.** `BusinessPolicy`, `ListingPolicy`, `UserPolicy`, `ListingReportPolicy`, `ContactRequestPolicy`, con `before()` para superadmin. Cada acción Livewire/controlador autoriza al inicio. Prohibidas las comprobaciones de rol dispersas por componentes o Blade.
- **Propiedad y autoría separadas:** `owner_user_id` (propietario), `created_by_user_id`, `updated_by_user_id` (trait `TracksAuthorship`). El superadmin puede crear/editar recursos de cualquier usuario sin convertirse en propietario y con trazabilidad en `audit_logs` y `listing_events`. Sin impersonación.
- **Estados explícitos con enums PHP** (`ListingStatus` con tabla de transiciones) y un Action por transición en `App\Actions\Listings\*`. Los Actions deciden *si el estado lo permite*; las Policies deciden *quién puede*. Ver `docs/06-listing-lifecycle.md`.
- **Configuración central en `config/avytra.php`** (umbrales de vigencia, mapa, geocodificación, límites, soporte). Prohibidos los números mágicos.
- **Laravel idiomático, sin sobrearquitectura:** Eloquent, Form Objects/Form Requests, Policies, enums, Actions con responsabilidad clara, Jobs, Notifications; Events solo con varios listeners reales. Nada de repositorios/managers/interfaces genéricas. Tampoco lógica de negocio en Blade ni en componentes Livewire (estos orquestan: autorizar → validar → Action → feedback).
- **Sin monetización en el MVP** (ni Stripe, planes, destacados, comisiones), pero sin decisiones que la hagan imposible después.
- **Preparado para i18n sin implementarla:** cadenas de UI con `__()` y claves en inglés natural, traducción en `lang/es.json`, `APP_LOCALE=es`, zona horaria `Europe/Madrid`, moneda EUR con columna `currency`.
- Búsqueda SQL con paginación siempre; sin Scout ni motores externos en el MVP.

## Reglas de UI

- **Flux-first:** usar componentes Flux/Flux Pro (formularios, selects, modales, tablas, badges, file upload, autocomplete, phone, pillbox, tabs, toast, pagination, breadcrumbs…) antes de crear componentes propios. Componentes propios solo en Blade y solo para lo que Flux no cubre (`x-listing-card`, `x-price`, `x-freshness-badge`, `x-map.*`, `x-empty-state`).
- **Livewire-first:** sin JavaScript adicional cuando Livewire, Alpine (incluido) o Flux lo resuelven. Única excepción prevista: el módulo de mapa (MapLibre) en páginas con mapa.
- **Identidad AVYTRA** (`docs/14-ui-design-system.md`, manual en `docs/design-system/`): Ink `#101828` como base y contraste, Lime `#B8F34A` solo para acciones primarias y acentos (nunca fondos grandes ni texto sobre blanco), Transfer Blue `#4E6BFF` para enlaces e información, Mist `#F5F7FA` fondos, Slate `#667085` texto secundario. Tipografía Lato. Radios 12/18/24. Luminosa, con aire, B2B; no plantilla de admin genérica ni clasificados. No alterar la identidad sin ADR.
- Tres contextos visualmente diferenciados bajo la misma marca: marketplace público (`layouts/public`), panel de usuario (`layouts/app`), administración (`layouts/app` variante admin, Ink).
- Mobile-first, responsive en todo. Estados vacíos y errores siempre diseñados. Textos visibles en español, tono directo y profesional (sin "chollo", "urgente", promesas).
- Wizard de publicación progresivo (8 pasos, borrador persistente por paso); nunca formularios gigantes.

## Reglas de seguridad y privacidad

- Nada es público por existir en base de datos. Visibilidad modelada explícitamente: `location_visibility`, `price_disclosure`, `disclosure` por métrica, `website_visibility`, `show_legal_form`. Toda salida pública (ficha, tarjeta, JSON-LD, sitemap, mapa) pasa por el presentador público del listing; prohibido pasar modelos completos a vistas públicas o serializarlos.
- Coordenadas reales, dirección, código postal, razón social, email/teléfono/nombre de la cuenta: nunca en HTML ni JSON público. El mapa usa solo `public_latitude/public_longitude/public_radius_m`.
- Contacto público = únicamente los campos `contact_*` de la publicación; teléfono/WhatsApp revelados tras clic con rate limit. El email nunca se muestra: los interesados escriben por el formulario relay (`contact_requests`, ADR-019).
- `#[Fillable]` explícito; campos de ciclo de vida y de propiedad fuera de `fillable` (solo Actions los escriben). `#[Locked]` en IDs de Livewire. Re-autorizar en cada acción, no solo en `mount`.
- Rate limiting nombrado en registro, reportes, revelación de contacto, búsqueda, uploads, enlaces firmados, geocodificación.
- Uploads: solo jpg/png/webp, tamaño y dimensiones validados, reencodificación a WebP (sin EXIF); el original nunca se enlaza públicamente.
- Salida escapada con `{{ }}`; `{!! !!}` prohibido con contenido de usuario; descripciones en texto plano.
- Admin en `/admin` con middleware que responde 404 a no superadmin. Superadmin asignado solo por comando Artisan; 2FA recomendado.
- Acciones administrativas sobre recursos ajenos → `audit_logs` con `on_behalf_of_user_id`.
- Nunca borrar publicaciones por inactividad: pausar (`expired`) y permitir reactivar.

## Convenciones de código

- Clases, tablas, columnas, rutas internas, métodos y tests en **inglés** (`Business`, `Listing`, `BusinessType`, `ListingStatus`); nunca `Empresa`/`Anuncio` en PHP. Textos visibles en español vía `lang/es.json`. Rutas públicas en español (`/empresas/{slug}`).
- Seguir las convenciones del starter kit y de los archivos hermanos (single-file Livewire en `resources/views/pages/`, Forms en `app/Livewire/Forms/`, Actions en `app/Actions/`).
- `php artisan make:*` para crear archivos; `vendor/bin/pint --dirty --format agent` tras tocar PHP; Larastan sin bajar el nivel.
- Tests Pest con nombres descriptivos, factories con estados, datasets para tablas de transiciones/visibilidad. Prioridad: reglas de negocio (policies, ownership, superadmin, estados, vigencia, visibilidad, contacto, filtros, rutas públicas). Sin cobertura artificial.
- Documentación en español; solo se crean documentos nuevos si el propietario lo pide o una fase lo requiere.

## Lo que NO se hace

Sobrearquitectura; paquetes "porque sí"; dashboards con gráficos; efectos y JS innecesarios; lógica en Blade; permisos dispersos; formularios gigantes; pedir datos innecesarios; publicar datos privados; código duplicado; valores hardcodeados; páginas SEO vacías; funcionalidades del roadmap (`docs/21-future-roadmap.md`) antes del MVP.

## Mapa de documentación

`docs/00-project-overview.md` (índice y estado del stack) · `01` visión · `02` alcance MVP · `03` roles y permisos · `04` dominio · `05` campos · `06` ciclo de vida · `07` base de datos · `08` público · `09` panel y admin · `10` ubicación y mapas · `11` online · `12` contacto · `13` vigencia · `14` diseño · `15` SEO · `16` seguridad · `17` medios · `18` tests · `19` arquitectura · `20` fases · `21` roadmap · `22` referencia ParkingParaCamiones · `23` despliegue y operación · `DECISIONS.md` · `STATUS.md` · `CHANGELOG.md`.

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
