# 00 — Visión general del proyecto

## Qué es AVYTRA

AVYTRA es una plataforma web gratuita para descubrir, publicar, vender y traspasar empresas y negocios.

- **Marca:** AVYTRA
- **Claim:** Empresas que cambian de manos.
- **Línea de acción:** Compra. Vende. Continúa.
- **Descriptor:** Marketplace de empresas y negocios.

AVYTRA es escaparate y punto de conexión. No interviene en la transacción, no cobra comisión y en su primera versión no monetiza nada. Su valor diferencial frente a un portal de clasificados es la **confianza**: cada publicación muestra cuándo se confirmó por última vez que el negocio sigue disponible, y las publicaciones sin confirmación se pausan automáticamente en lugar de acumularse como anuncios abandonados.

## Principios de producto

1. **Publicar fácil.** Una persona sin experiencia técnica debe poder poner su negocio en venta con un wizard corto y progresivo.
2. **Mantener fácil.** Renovar la vigencia de una publicación cuesta un clic.
3. **Encontrar fácil.** Quien busca debe entender en segundos qué se vende, dónde, cuánto cuesta, qué incluye, si sigue disponible y cómo contactar.
4. **Confianza.** "Disponibilidad confirmada hace 8 días" es una característica distintiva, no un detalle.
5. **Privacidad explícita.** Ningún dato se muestra públicamente por el hecho de existir en base de datos. Ubicación, cifras, razón social y contacto tienen visibilidad modelada.
6. **Asistencia humana.** El superadministrador puede crear y gestionar recursos en nombre de cualquier usuario, con trazabilidad, para ayudar por teléfono a quien lo necesite.

## Conceptos clave del dominio

| Concepto | Descripción |
|---|---|
| `User` | Persona registrada. Puede ser `user` o `superadmin`. |
| `Business` | La empresa o negocio real. Pertenece a un usuario. Persiste aunque se venda. |
| `Listing` | La publicación mediante la cual un `Business` se ofrece en venta, traspaso o búsqueda de socio/inversor. Un `Business` puede tener histórico de `Listing`. |
| `Location` | Ubicación geográfica de un negocio físico o híbrido, con nivel de visibilidad configurable. |
| `OnlineProfile` | Datos específicos de un negocio online o híbrido. |
| `Category` | Taxonomía de sectores (dos niveles). |

La separación `Business` ≠ `Listing` es la decisión arquitectónica más importante del proyecto. Ver [04-domain-model.md](04-domain-model.md) y `DECISIONS.md` (ADR-001).

## Stack (versiones reales instaladas a 2026-09-21)

| Componente | Versión | Notas |
|---|---|---|
| PHP | 8.4.15 | `composer.json` exige `^8.3` |
| Laravel Framework | 13.32.0 | |
| Livewire | 4.4.5 | Componentes single-file (`⚡nombre.blade.php`) y `Route::livewire()` |
| Flux UI | 2.20.0 | Free |
| Flux UI Pro | 2.20.0 | Licencia configurada en `auth.json` |
| Laravel Fortify | 1.39.0 | Registro, reset, verificación de email, 2FA, passkeys |
| Livewire Blaze | 1.0.19 | Optimización de componentes Blade |
| Pest | 5.2.1 | Con `pest-plugin-laravel` 5.0.1 |
| Larastan | 3.12.2 | `phpstan.neon` presente |
| Pint | 1.32.1 | `pint.json` presente |
| Tailwind CSS | ^4.1 | Vía `@tailwindcss/vite` |
| Vite | ^8.0 | Con `vite-plus` |
| Base de datos | MySQL/MariaDB | `.env` local apunta a `avytra` |
| Tests | SQLite en memoria | Configurado en `phpunit.xml` |

No hay ningún paquete de mapas, imágenes, SEO, roles ni auditoría instalado. Cualquier dependencia nueva requiere aprobación previa y queda registrada en `DECISIONS.md`.

## Estado actual del repositorio (Phase 0)

El repositorio contiene el **Laravel Livewire Starter Kit** oficial sin modificar, con:

- Autenticación completa vía Fortify (login, registro, reset de contraseña, verificación de email, 2FA con confirmación, passkeys).
- Layout de aplicación con sidebar (`resources/views/layouts/app/sidebar.blade.php`) y layouts de auth (`card`, `simple`, `split`).
- Páginas de ajustes (perfil, apariencia, seguridad) como componentes Livewire single-file en `resources/views/pages/settings/`.
- Dashboard vacío (`resources/views/dashboard.blade.php`).
- Página `welcome` del starter kit.
- Tests Pest de auth, dashboard y settings (14 archivos).
- CI en GitHub Actions (`composer ci:check` = Pint + Larastan + tests).

Detalles detectados durante la inspección y **corregidos en Phase 1**: `.env.example` con conexión inexistente, locales en inglés, zona horaria UTC, fuente Instrument Sans, `class="dark"` fijo, enlaces del starter kit y README vacío. Ver `CHANGELOG.md`.

## Recursos de marca

En `docs/design-system/`:

- `AVYTRA_Brand_Identity_v1.pdf` — manual de marca (16 páginas, fuente de verdad).
- `AVYTRA_brand_copy.md` — esencia, paleta, tipografía, voz.
- `AVYTRA_design_tokens.json` — colores, tipografía, radios.
- `avytra-logo-primary.svg`, `avytra-logo-white.svg`, `avytra-logo-monochrome.svg`, `avytra-symbol.svg`, `avytra-app-icon.svg`.

Ver [14-ui-design-system.md](14-ui-design-system.md).

## Mapa de la documentación

| Archivo | Contenido |
|---|---|
| `01-product-vision.md` | Posicionamiento, usuarios, propuesta de valor, tono |
| `02-mvp-scope.md` | Qué entra y qué no entra en el MVP |
| `03-users-roles-permissions.md` | Roles, propiedad, autoría, Policies |
| `04-domain-model.md` | Entidades, relaciones, enums, invariantes |
| `05-business-fields.md` | Campos de empresa y publicación, obligatoriedad y visibilidad |
| `06-listing-lifecycle.md` | Estados y máquina de estados de `Listing` |
| `07-database-design.md` | Esquema de tablas propuesto |
| `08-public-pages-and-flows.md` | Home, explorar, ficha, tarjeta, buscador |
| `09-dashboard-and-admin.md` | Panel de usuario, wizard, administración |
| `10-location-and-maps.md` | Ubicación, visibilidad, mapas, geocodificación |
| `11-online-businesses.md` | Modelo de negocios online e híbridos |
| `12-contact-system.md` | Métodos de contacto y visibilidad |
| `13-freshness-and-notifications.md` | Vigencia, recordatorios, pausa automática |
| `14-ui-design-system.md` | Identidad aplicada a Flux/Tailwind |
| `15-seo.md` | URLs, metadatos, indexación, sitemap |
| `16-security-and-privacy.md` | Seguridad y privacidad |
| `17-media-strategy.md` | Logo, portada, galería |
| `18-testing-strategy.md` | Estrategia de tests |
| `19-technical-architecture.md` | Arquitectura Laravel/Livewire |
| `20-development-phases.md` | Hoja de ruta ejecutable por fases |
| `21-future-roadmap.md` | Ideas fuera del MVP |
| `22-parkingparacamiones-reference.md` | Análisis del proyecto de referencia |
| `23-deployment.md` | Despliegue, procesos permanentes, copias de seguridad, smoke test |
| `DECISIONS.md` | ADRs |
| `STATUS.md` | Estado del proyecto entre sesiones |
| `CHANGELOG.md` | Cambios por fase |
