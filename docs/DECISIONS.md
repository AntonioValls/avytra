# AVYTRA — Architecture Decision Records

ADR ligero. Solo decisiones arquitectónicas relevantes. Formato: contexto, decisión, consecuencias. Una decisión nueva que contradiga la documentación se registra aquí **antes** de implementarse y se actualiza el documento afectado.

Estados: Proposed · Accepted · Superseded · Rejected.

---

## ADR-001 — Separar Business y Listing

Status: Accepted
Date: 2026-09-21

### Context
Una empresa es un hecho estable; ponerla en venta es un acto puntual que puede repetirse, cerrarse o pausarse. Mezclar ambos en una tabla obliga a borrar la empresa al vender o a reutilizar registros con historial confuso.

### Decision
`Business` (empresa, propiedad de un `User`) y `Listing` (publicación de esa empresa con operación, precio, contacto y ciclo de vida). Una empresa tiene 0..N publicaciones, con como máximo una no terminada. El propietario de una publicación es siempre el de su empresa. Ubicación y perfil online pertenecen a la empresa; precio, cifras, contacto y estado a la publicación.

### Consequences
Histórico de ventas por empresa; "publicar de nuevo" sin re-teclear; el wizard crea ambos en un flujo. Requiere validación de coherencia (tipo de empresa ↔ ubicación/perfil online) en el momento de publicar.

---

## ADR-002 — Roles como enum en `users.role`, sin paquete de permisos

Status: Accepted
Date: 2026-09-21

### Context
Dos roles (`user`, `superadmin`); permisos derivados de la propiedad.

### Decision
Columna `role` + enum `UserRole`; Policies con `before()` para superadmin; middleware `EnsureUserIsSuperadmin` (404). Sin spatie/laravel-permission. Asignación del rol por comando Artisan.

### Consequences
Cero dependencias; tests simples. Si aparecen roles intermedios (moderador, asesor), nuevo ADR.

---

## ADR-003 — IDs autoincrementales; slugs para lo público

Status: Accepted
Date: 2026-09-21

### Context
UUID/ULID solo aportan valor si hay que ocultar volúmenes, generar IDs en cliente o federar. Ninguno aplica.

### Decision
`bigint` autoincremental en todas las tablas. Público por `slug` (con historial de redirecciones). IDs solo en rutas autenticadas protegidas por Policies.

### Consequences
Índices y joins simples; enumeración inocua porque la autorización es por Policy y las respuestas 404 no distinguen "no existe" de "no autorizado".

---

## ADR-004 — Ubicación con coordenadas privadas y públicas derivadas

Status: Accepted
Date: 2026-09-21

### Context
La ubicación de un negocio en venta es sensible. El mapa no debe fingir exactitud.

### Decision
Tabla `locations` con `latitude/longitude/address_line/postal_code` privados y `public_latitude/public_longitude/public_radius_m` derivados según `location_visibility` (`exact`, `approximate` con desplazamiento determinista 250–600 m y círculo de 700 m, `city_only` con centroide del municipio, `hidden`). Catálogo `regions/provinces/municipalities` con centroides desde archivos de datos. Toda salida pública usa únicamente `public_*`.

### Consequences
Sin geocodificación obligatoria; el vendedor coloca el pin. Sal configurable para regenerar puntos públicos. Un test garantiza que las coordenadas privadas nunca llegan a HTML/JSON público.

---

## ADR-005 — Mapa con MapLibre GL JS y estilo de OpenFreeMap configurable

Status: Accepted (implementación en Phase 5, requiere aprobación de la dependencia npm)
Date: 2026-09-21

### Context
Se evaluaron Leaflet+raster (no hay proveedor raster gratuito apto para producción comercial sin key), Google Maps (coste y términos) y MapLibre+OpenFreeMap (sin key ni cuota, usado en ParkingParaCamiones).

### Decision
MapLibre GL JS; estilo por defecto OpenFreeMap `liberty` en `config('avytra.map.style_url')`; atribución visible; fallback HTML sin WebGL; módulo JS único desacoplado de Livewire; colores desde tokens. Ningún endpoint público masivo de coordenadas.

### Consequences
Cambio de proveedor (MapTiler, servidor propio) por configuración. Riesgo aceptado: OpenFreeMap sin SLA. Verificar en Phase 5 la resolución del worker con Vite 8; prohibido vendorizar a mano.

---

## ADR-006 — Medios con Spatie Media Library (propuesta)

Status: Proposed (se acepta o rechaza al iniciar Phase 6)
Date: 2026-09-21

### Context
Se necesitan colecciones (logo/portada/galería), conversiones a varios tamaños, orden, alt, borrado seguro y WebP. ParkingParaCamiones con GD puro acabó sin thumbnails.

### Decision
Proponer `spatie/laravel-medialibrary` con conversiones en cola. Alternativa B (modelo propio + intervention/image) con el mismo contrato de uso si se rechaza.

### Consequences
Dependencia externa mantenida por Spatie; tabla `media` polimórfica. Requiere aprobación explícita del propietario (regla del proyecto sobre dependencias).

---

## ADR-007 — Vigencia con confirmación de un clic sin login y pausa automática configurable

Status: Accepted
Date: 2026-09-21

### Context
Evitar anuncios abandonados sin perder datos ni exigir habilidades técnicas al vendedor.

### Decision
Umbrales en `config/avytra.php` (45/55/60 días por defecto). Comando horario idempotente con flags `first/second_reminder_sent_at`. Estado `expired` distinto de `paused`. Confirmación por enlace firmado temporal **sin login** (GET), además de panel y admin. Nunca se borra por inactividad.

### Consequences
Riesgo aceptado: un email reenviado permite a un tercero prolongar la vigencia (impacto mínimo). Las notificaciones van al email de la cuenta, no al contacto de la publicación.

---

## ADR-008 — Zona horaria de aplicación Europe/Madrid

Status: Accepted
Date: 2026-09-21

### Context
Producto monopaís; plazos en días; Laravel guarda timestamps en la zona de la app.

### Decision
`app.timezone = Europe/Madrid`, `APP_LOCALE = es`. Se acepta la ambigüedad horaria del cambio de otoño.

### Consequences
Scheduler y fechas mostradas coinciden con la percepción del usuario sin conversiones. Si AVYTRA se internacionaliza, se reconsiderará (UTC + conversión por usuario).

---

## ADR-009 — Contacto almacenado en la publicación, no en el perfil

Status: Accepted
Date: 2026-09-21

### Context
Evitar exponer datos del usuario; permitir contacto distinto del propietario de la cuenta; asistencia por el superadmin a personas sin email.

### Decision
Columnas `contact_*` y `preferred_contact_method` en `listings`. Todo canal relleno es público. Prefill visible y editable en el wizard; nunca copia silenciosa. Teléfono/email revelados tras clic con rate limit.

### Consequences
Sin mensajería interna; sin flags `show_*` redundantes. Los recordatorios de vigencia usan el email de la cuenta.

---

## ADR-010 — Cifras económicas como métricas con divulgación por fila

Status: Accepted
Date: 2026-09-21

### Context
Ocho métricas × (valor, min, max, modo) en columnas serían 30+ columnas casi siempre vacías.

### Decision
`listings.asking_price*` + `price_disclosure` como columnas (filtrable/ordenable). Resto en `listing_financial_metrics (metric, disclosure, amount, min, max)`. Importes enteros en EUR con `currency`.

### Consequences
Añadir métricas sin migraciones; filtrar por facturación requiere join (fuera del MVP). Vista pública construida desde el presentador que respeta `disclosure`.

---

## ADR-011 — Moderación posterior, sin estados de revisión previa en el MVP

Status: Accepted
Date: 2026-09-21

### Context
Plataforma gratuita con un solo administrador; la revisión previa frenaría el arranque.

### Decision
Publicación inmediata; `suspended` por el superadmin; reportes públicos con rate limit; listado de publicaciones nuevas en admin. `pending_review`/`rejected` en roadmap.

### Consequences
Riesgo de contenido inapropiado visible unas horas; mitigado por reportes y revisión diaria.

---

## ADR-012 — Auditoría propia y proporcionada, sin paquete

Status: Accepted
Date: 2026-09-21

### Context
Se necesita saber quién creó/modificó y qué hizo el superadmin; no un log de cada campo de cada modelo.

### Decision
`created_by/updated_by` (trait), `listing_events` para transiciones y `audit_logs` para acciones administrativas sobre recursos ajenos y cambios sensibles. Sin spatie/laravel-activitylog.

### Consequences
Dos tablas y un trait; si se requiere diff completo universal, nuevo ADR.

---

## ADR-013 — Búsqueda SQL en el MVP

Status: Accepted
Date: 2026-09-21

### Context
Volumen inicial pequeño; evitar infraestructura.

### Decision
Filtros por columnas indexadas y `LIKE` sobre título/nombre; FULLTEXT en MySQL cuando haga falta; sin Scout/Meilisearch. Paginación obligatoria.

### Consequences
Simplicidad; los modelos pueden hacerse `Searchable` más adelante sin cambios estructurales.

---

## ADR-014 — Claves de traducción en inglés natural con `lang/es.json`

Status: Accepted
Date: 2026-09-21

### Context
UI en español hoy; preparación para i18n sin implementarla. El starter kit usa `__('Dashboard')`.

### Decision
Cadenas fuente en inglés dentro de `__()`, traducción en `lang/es.json`, `APP_LOCALE=es`. Enums con `label()` traducible. Rutas en español fijas en MVP.

### Consequences
Añadir un idioma = añadir un JSON. Se evita el error de ParkingParaCamiones de usar texto español como clave.
