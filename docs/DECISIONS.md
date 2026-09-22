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

## ADR-007 — Vigencia con confirmación autenticada de un botón y pausa automática configurable

Status: Accepted (revisado 2026-09-21: se descarta la confirmación sin login propuesta inicialmente, a petición del propietario)
Date: 2026-09-21

### Context
Evitar anuncios abandonados sin perder datos ni exigir habilidades técnicas al vendedor. Se valoró un enlace firmado que confirmara sin sesión (máxima sencillez) frente a exigir login (actor identificado, sin GET mutante, sin riesgo por reenvío de email).

### Decision
Umbrales en `config/avytra.php` (45/55/60 días por defecto). Comando horario idempotente con flags `first/second_reminder_sent_at`. Estado `expired` distinto de `paused`. El email enlaza (firma temporal) a una página del panel **bajo `auth` y Policy** con un único botón "Sí, sigue disponible" (POST). También desde el panel y, en nombre del propietario, desde admin. Nunca se borra por inactividad.

### Consequences
Cada confirmación tiene actor real y queda en `listing_events`. Más fricción para usuarios poco tecnológicos, mitigada con passkeys, reset de contraseña visible en esa pantalla y confirmación por el superadmin para cuentas asistidas. Magic links en roadmap si la fricción se confirma.

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

---

## ADR-015 — Una publicación puede ofrecer varios tipos de operación

Status: Accepted
Date: 2026-09-21

### Context
El propietario debe poder ofrecer a la vez, por ejemplo, venta completa y entrada de socio, o solo una venta parcial. Se valoró permitir varias publicaciones activas por empresa (una por operación) frente a una publicación con varios tipos.

### Decision
Se mantiene la invariante de **una publicación no terminada por empresa**. Los tipos ofrecidos se guardan en la tabla pivote `listing_operation_types` (≥1), con `listings.primary_operation_type` para badge, título sugerido y orden, y campos opcionales `stake_percent` y `operation_notes` para operaciones parciales. Se añade `partial_sale` al enum `OperationType`.

### Consequences
Una sola ficha por empresa (sin contenido duplicado ni SEO fragmentado), una sola confirmación de vigencia y un solo contacto. El filtro público por operación consulta la pivote. El wizard usa selección múltiple con elección de principal.

---

## ADR-016 — Propiedad directa por usuario; sin equipos ni asesores en el MVP

Status: Accepted
Date: 2026-09-21

### Context
No está claro si asesores o brokers gestionarán carteras ajenas. Modelar equipos ahora (patrón ParkingParaCamiones) añadiría tablas, invitaciones y UI sin demanda confirmada.

### Decision
`businesses.owner_user_id` apunta directamente a un `User`. Un asesor que hoy quiera publicar en nombre de un cliente puede hacerlo desde su propia cuenta (varias empresas por usuario) y, si el cliente se registra después, el superadmin transfiere la propiedad (`TransferBusinessOwnership`, con audit).

### Consequences
Simplicidad máxima en Policies y panel. Ruta de migración documentada en el roadmap: si llegan equipos, se crea `teams` con un equipo personal por usuario y se migra `owner_user_id` → `owner_team_id` en una sola migración de datos; el resto del dominio (`listings`, `locations`, medios) no cambia porque cuelga de `businesses`.

---

## ADR-017 — Eliminar la cuenta elimina las empresas del usuario

Status: Accepted
Date: 2026-09-22

### Context
El starter kit permite eliminar la cuenta desde Ajustes. Con empresas (y, desde Phase 3, publicaciones) colgando del usuario había que decidir entre bloquear la eliminación mientras existan recursos, dejar empresas huérfanas (`owner_user_id` nulo) o archivar y anonimizar en cascada. Las empresas huérfanas complican todas las Policies; bloquear la eliminación contradice el derecho de supresión del RGPD; conservar publicaciones anonimizadas aporta poco (solo un 410 en vez de un 404).

### Decision
Eliminar la cuenta borra de verdad las empresas del usuario (también las que estén en papelera), con su ubicación y perfil online por cascada de base de datos. Lo ejecuta el Action `App\Actions\Users\DeleteUserAccount`, único camino para borrar un usuario; la FK `businesses.owner_user_id` es `restrictOnDelete` como red de seguridad para que nada borre un usuario sin pasar por el Action. En Phase 3 el Action archivará las publicaciones (evento `archived`) y las borrará junto con la empresa; en Phase 6, sus imágenes. Las empresas que ese usuario creó **para otros** (superadmin) no se tocan: solo pierden `created_by_user_id` (`nullOnDelete`). Los `audit_logs` conservan la entrada con `actor_user_id` nulo.

La regla "nunca borrar publicaciones por inactividad" sigue vigente: aplica a la plataforma, no a la voluntad explícita del usuario, que confirma con su contraseña.

### Consequences
Sin registros huérfanos ni estados especiales en Policies. Se pierde el histórico de esa cuenta, coherente con la supresión solicitada. Si más adelante se necesita retener publicaciones vendidas por motivos legales o estadísticos, se registrará un nuevo ADR con anonimización explícita.
