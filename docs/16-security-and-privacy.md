# 16 — Seguridad y privacidad

## Autorización

- **Policies** (`BusinessPolicy`, `ListingPolicy`, `UserPolicy`, `ListingReportPolicy`) son la única fuente de verdad. Ver [03-users-roles-permissions.md](03-users-roles-permissions.md).
- Cada acción Livewire que muta un recurso empieza con `$this->authorize(...)`. Cada método `mount()` de componentes con recurso también.
- Middleware `EnsureUserIsSuperadmin` para `/admin/*`; responde **404**, no 403 (no revela el panel).
- Livewire: las propiedades públicas que representan modelos se cargan por ID y se re-autorizan en cada acción; nunca se confía en propiedades públicas para decidir permisos (un cliente puede modificarlas). Propiedades sensibles se declaran `#[Locked]`.
- `Gate::before` global **no** se usa; cada Policy tiene su `before()` para superadmin, explícito y testeable.

## Validación

- Form Objects de Livewire (`App\Livewire\Forms\*`) por paso del wizard y por formulario; Form Requests en los pocos controladores clásicos (reportes, confirmación por enlace).
- Reglas de coherencia como `Rule` personalizadas: `PriceDisclosureIsConsistent`, `PreferredContactHasChannel`, `LocationRequiredForType`.
- Enums validados con `Rule::enum()`.
- URLs con `url:http,https`; emails con `email:rfc,dns` en producción; teléfonos normalizados a E.164.
- Longitudes máximas en toda columna de texto (evita truncados silenciosos y abuso).

## Mass assignment

- Modelos con `#[Fillable]` explícito (convención Laravel 13 ya usada en `User`). Nunca `$guarded = []`.
- Campos de ciclo de vida (`status`, `published_at`, `last_confirmed_at`, `*_by_user_id`, `owner_user_id`) **fuera de `fillable`**; solo los Actions los escriben con asignación directa.
- Los Form Objects mapean explícitamente qué campos persisten.

## Rate limiting

| Recurso | Límite |
|---|---|
| Login, 2FA, passkeys | Existentes en `FortifyServiceProvider` |
| Registro | 5/hora por IP (se añade) |
| Reportar publicación | 3/hora por IP + 1 abierto por listing e IP |
| Revelar teléfono/email | 20/hora por IP |
| Página de confirmación desde email | 30/hora por usuario |
| Búsqueda pública | 60/min por IP (evita scraping agresivo) |
| Subida de imágenes | 30/hora por usuario |
| Geocodificación | 1/s global + 20/hora por usuario |

Definidos en `AppServiceProvider` con nombres (`register` y `public` desde Phase 1), aplicados con `throttle:{nombre}` o `RateLimiter` en acciones Livewire. Fortify no permite asignar un limitador solo a la ruta de registro por configuración; el limitador `register` se aplicará con un middleware propio sobre `POST /register` (o junto al honeypot) en Phase 10.

## Uploads

- Validación `image`, `mimes:jpg,jpeg,png,webp`, `max:8192` (KB), dimensiones mínimas 600×400 y máximas 8000×8000.
- Reencodificación **siempre** (se genera WebP a partir del original; el archivo original subido no se sirve directamente): elimina metadatos EXIF (incluida geolocalización) y neutraliza payloads en imágenes.
- Nombres de archivo aleatorios; ruta por `business_id`; disco `public` en MVP (o S3-compatible vía config).
- Sin SVG de usuario.

## XSS y salida

- Todo texto de usuario se renderiza con `{{ }}` (escapado). `{!! !!}` prohibido para contenido de usuario.
- Descripciones en texto plano; párrafos con `nl2br(e($text))` o componente `x-prose-text`.
- Sin `flux:editor` en MVP (evita sanitización HTML). Si se incorpora, se añadirá un sanitizador con lista blanca y ADR.
- Los `data-*` que consume el mapa se generan con `Js::from()` / `json_encode` con flags `JSON_HEX_*`.
- CSP en Phase 10 (dificultada por Livewire/Alpine inline; se evaluará `nonce`).

## CSRF

Cubierto por Livewire y por `@csrf` en formularios clásicos. No existe ninguna ruta GET que mute estado: la confirmación de vigencia desde el email aterriza en una página autenticada y la acción se ejecuta por POST (ADR-007).

## Enumeración de recursos y URLs

- IDs numéricos internos; público por slug. Los IDs aparecen solo en rutas autenticadas protegidas por Policies (`/panel/publicaciones/{listing}`), donde enumerar no da acceso.
- Respuestas 404 idénticas para "no existe" y "no autorizado" en la parte pública.
- No se exponen recuentos privados ni endpoints JSON públicos con datos masivos. El mapa recibe solo los puntos de la página actual y solo coordenadas públicas.

## Spam y bots

- Honeypot + tiempo mínimo de envío en reporte y registro.
- Registro con verificación de email obligatoria para publicar (`verified` ya en rutas).
- Publicaciones nuevas visibles en `/admin` (últimas 24 h) para revisión rápida posterior.
- Sin CAPTCHA en el MVP; si aparece spam, se evalúa Turnstile (sin coste) con ADR.
- Enlaces salientes de usuario con `rel="nofollow noopener ugc"`.

## Emails

- Solo se envía a emails de cuenta verificados o a los introducidos por el superadmin al crear usuarios.
- Enlaces firmados con expiración; sin tokens de sesión en URLs.
- Cabeceras `List-Unsubscribe` para recordatorios (opción "no recibir recordatorios" no existe: son necesarios para mantener la publicación; en su lugar, el email explica cómo pausar o archivar).

## Privacidad de datos

Inventario de datos sensibles y dónde se decide su visibilidad:

| Dato | Almacén | Visibilidad |
|---|---|---|
| Dirección exacta, CP, coordenadas reales | `locations` | Solo con `location_visibility = exact` (dirección) — coordenadas reales **nunca** se sirven; se sirven `public_*` |
| Razón social | `businesses.legal_name` | Nunca pública en MVP |
| Forma jurídica | `businesses.legal_form` | `show_legal_form` |
| Web y redes | `businesses.website_url`, `online_profiles.social_profiles` | `website_visibility` |
| Cifras económicas | `listings.asking_price*`, `listing_financial_metrics` | `price_disclosure`, `disclosure` por métrica |
| Teléfono/email de contacto | `listings.contact_*` | Público (por definición), revelado tras clic |
| Email y teléfono de la cuenta | `users` | Nunca públicos |
| Identidad del vendedor | `users.name` | Nunca pública; solo `contact_name` de la publicación |
| Visitas mensuales | `online_profiles` | `monthly_visits_disclosure` |

**Proyección pública obligatoria:** la ficha, la tarjeta, el JSON-LD, el sitemap y cualquier salida pública se construyen desde `App\Support\Listings\PublicListingPresenter` (o similar), que recibe el `Listing` y expone únicamente atributos ya filtrados por visibilidad. Está prohibido pasar el modelo Eloquent completo a vistas públicas o serializarlo a JSON. Un test recorre las vistas públicas y afirma que no aparecen `legal_name`, `latitude`, `address_line` (salvo `exact`), email del usuario, etc.

## Auditoría (estrategia proporcionada)

- `created_by_user_id`/`updated_by_user_id` en `businesses` y `listings` (trait `TracksAuthorship`).
- `listing_events` para todas las transiciones y confirmaciones (con actor y `on_behalf_of`).
- `audit_logs` para: cualquier escritura del superadmin sobre recursos de otro usuario (con diff de campos relevantes), cambio de propietario, suspensión/levantamiento, creación de usuarios por admin, resolución de reportes, cambio manual de slug.
- No se registra cada edición del propio propietario sobre sus recursos (proporcionalidad), ni pulsaciones.
- Sin paquete externo: `spatie/laravel-activitylog` se evaluó; ofrece más de lo necesario (logging de todos los modelos, causer automático) y añadiría dependencia para lo que son dos tablas y un trait. Si la auditoría crece (p. ej. diff completo de todo), se reconsidera en ADR.

## Cumplimiento (RGPD, básico)

- Páginas de aviso legal, privacidad y cookies con texto real antes del lanzamiento (responsabilidad del propietario del proyecto; se dejan plantillas).
- Cookies: solo técnicas (sesión, XSRF) en MVP → banner informativo simple, sin gestor de consentimiento. Si se añaden analíticas, se incorpora consentimiento.
- Eliminación de cuenta (starter kit): en Phase 2 se define el efecto sobre empresas/publicaciones (archivar y anonimizar contacto). Los `audit_logs` conservan `actor_user_id` en `null` tras borrado (nullOnDelete).
- Exportación de datos: fuera del MVP.

## Superadmin

- Cuenta con 2FA obligatorio (se verifica en Phase 1 con middleware o comprobación en login para `superadmin`; si Fortify no lo permite fácilmente, se documenta como política).
- Asignación solo por comando Artisan.
- Todas sus acciones sobre recursos ajenos quedan en `audit_logs`.

## Dependencias y secretos

- `auth.json` (credenciales Flux Pro) **no debe** estar en el repositorio. Verificado en Phase 0: está en `.gitignore` y no está trackeado.
- Secretos solo en `.env`; `APP_KEY` rotable con `APP_PREVIOUS_KEYS`.
- `composer audit` y `npm audit` en CI (Phase 10).
