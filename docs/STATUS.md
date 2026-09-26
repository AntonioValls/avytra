# AVYTRA — Project Status

## Current phase

**Phase 11 — Formulario de contacto relay: implementada y verificada (tests y navegador). Pendiente de revisión del propietario.** La primera mejora del roadmap convertida en fase (ADR-019): el email de contacto deja de mostrarse en público y los interesados escriben desde un formulario; el vendedor recibe el mensaje por email (con `Reply-To`) y en `/panel/mensajes`.

Phase 10 sigue pendiente solo del lanzamiento (checklist abajo, tareas del propietario). Phase 11 se eligió el 2026-09-26 entre las mejoras de `docs/21` al pedir el propietario "la fase 11"; el diseño se aprobó antes de escribir código. Phase 9 se dio por aprobada el 2026-09-26 al pedir el propietario seguir con Phase 10. Phase 8 se dio por aprobada el 2026-09-26 al pedir seguir con Phase 9.

## Checklist de lanzamiento (Phase 10)

| Punto | Estado |
|---|---|
| Revisión de seguridad de docs/16 punto por punto (Policies, validación, mass assignment, rate limiting, uploads, XSS, CSRF, enumeración, spam, emails, privacidad, auditoría, superadmin, secretos) | ✅ Revisado; huecos cerrados en esta fase: limitador y honeypot de registro, `email:rfc,dns` en producción, cabeceras de seguridad, aviso de 2FA. CSP pospuesta (ADR-018) |
| `composer audit` / `npm audit` en CI | ✅ Paso "Audit dependencies" en `tests.yml`; en local ambos sin avisos (2026-09-26) |
| Rendimiento: `Model::preventLazyLoading` fuera de producción, índices, caché de agregados y sitemap, Blaze en tarjetas | ✅ `preventLazyLoading` desde Phase 1 (la suite lo vigila); índices de `listings` por estado y fechas; cachés de 15 min/1 h invalidadas en transiciones; Blaze en 5 componentes de presentación |
| Responsive final | ✅ Comprobado en móvil en cada fase (explorar, ficha, panel, confirmación, admin); pendiente ojo del propietario |
| Accesibilidad (contraste, foco, labels, alt) | ✅ Pasada automática en home y explorar: `lang`, un `h1`, imágenes con `alt`, botones e inputs con nombre; anillo de foco `focus-visible` en tarjetas y botones Flux. Contraste Lime sobre Ink por diseño (docs/14) |
| Textos legales definitivos | ⏳ Propietario (plantillas con aviso "texto provisional" en `/aviso-legal`, `/privacidad`, `/cookies`) |
| Emails probados en clientes reales | ⏳ Propietario, desde el proveedor de producción (tema `avytra` verificado en navegador) |
| Configuración de producción (worker, cron, disco de medios, backups, logs) | ✅ Documentada en `docs/23-deployment.md`; ⏳ aplicarla en el servidor |
| Monitorización del scheduler | ✅ Latido cada minuto + aviso rojo en `/admin`; `/up` para el monitor externo |
| Página de mantenimiento | ✅ `errors/503` de marca, verificada con `php artisan down --render="errors::503"` |
| Documentación de despliegue | ✅ `docs/23-deployment.md` |
| Smoke test en staging y despliegue de prueba | ⏳ Propietario, siguiendo `docs/23` (lista de 8 comprobaciones) |
| `composer test` | ✅ 501 tests en verde | Phase 7 se dio por aprobada el 2026-09-26 al pedir el inicio de Phase 8. Phase 6 se dio por aprobada el 2026-09-26 al pedir el inicio de Phase 7 (sin dependencias nuevas). Phase 5 se dio por aprobada el 2026-09-23 al pedir el inicio de Phase 6; esa petición se tomó como aprobación de `spatie/laravel-medialibrary` (ADR-006), igual que `maplibre-gl` en Phase 5.

## Completed

### Phase 0 — Documentación y arquitectura (2026-09-21)
- Documentos `docs/00` a `docs/22`, `DECISIONS.md` (ADR-001…016), `CHANGELOG.md`, `CLAUDE.md`. Análisis de ParkingParaCamiones.

### Phase 1 — Fundamentos (2026-09-21)
- Configuración, marca, layouts (`public`, `app` con área admin, `auth`), traducción, roles y comando `avytra:superadmin`, middleware `superadmin` (404), panel `/panel`, `/admin`, tabla `audit_logs` + `AuditLogger`, rate limiters `register` y `public`. Detalle en `CHANGELOG.md`.

### Phase 2 — Dominio de empresas (2026-09-22)
- Modelo `Business` + `Location` + `OnlineProfile`, catálogo geográfico y de sectores, `BusinessPolicy`, Actions de empresa, `PublicPointDeriver`, panel y admin de empresas. Detalle en `CHANGELOG.md`.

### Phase 3 — Publicaciones (2026-09-22)
- Tablas de publicaciones, enums, `ListingPolicy`, Actions de ciclo de vida, validador de publicabilidad, slugger, wizard de 8 pasos, panel y admin de publicaciones, `DemoBusinessSeeder`. Detalle en `CHANGELOG.md`.

### Phase 4 — Marketplace público (2026-09-22)
- `PublicListingPresenter` (única proyección pública), `PriceFormatter`, `MarketplaceAggregates`, home real, explorar con filtros en URL, ficha con contacto revelado bajo rate limit, reportes con bandeja admin, páginas estáticas y errores públicos. Detalle en `CHANGELOG.md`.

### Phase 5 — Ubicación y mapas (2026-09-23)
- `maplibre-gl` (ADR-005), componentes `x-map.listing`, `x-map.explore` y `x-map.picker`, `LocationPickerComponent`, geocodificación (`NullGeocoder`, `NominatimGeocoder`), `PublicListingPresenter::mapPoint()`. Detalle en `CHANGELOG.md`.

### Phase 6 — Medios (2026-09-23)
- `spatie/laravel-medialibrary` ^11.23 (ADR-006, driver GD). Originales en disco privado (`MEDIA_ORIGINALS_DISK=local`), conversiones WebP en el público (`MEDIA_DISK=public`) bajo `businesses/{business_id}/{media_id}/`; conversiones en cola tras el commit (`MEDIA_QUEUE_CONVERSIONS`).
- `Business implements HasMedia`: colecciones `logo`, `cover` (un archivo) y `gallery` (máx. 12) del enum `MediaCollection`; conversiones `thumb`, `card`, `detail`, `og` (solo portada) y `logo` con tamaños en `config/avytra.php` → `media`.
- Actions `App\Actions\Media\*` (añadir, quitar, reordenar, alt) con audit del superadmin; componente Livewire `businesses.images` en el paso 7 del wizard y en el formulario de empresa (subida inmediata con `flux:file-upload`, `wire:sort`, alt en línea, limitador `image-upload`, `wire:poll` mientras haya conversiones pendientes).
- Público: `PublicImage` + `PublicListingPresenter::{coverImage, galleryImages, logoImage, ogImageUrl}`; tarjetas con `srcset`, ficha con galería y lightbox, `og:image`, `Offer.image`; placeholder de marca sin imágenes o mientras la cola no ha generado la conversión.
- Verificado en el navegador integrado con imágenes generadas sobre la empresa de demo (ficha: portada, miniaturas, lightbox con flechas y pie; explorar: tarjeta con portada; móvil sin scroll horizontal; sin errores de consola; solo URLs `.webp` de conversión en el HTML). El paso 7 y el formulario solo están verificados con tests.

### Phase 7 — Sistema de vigencia (2026-09-26)
- Comando `avytra:listings:process-freshness` (`--dry-run`), scheduler hourly `withoutOverlapping`, enum `ReminderStage`, Actions `SendListingFreshnessReminder`, `ResendFailedReminder` y `ExpireListing` con notificación; notificaciones `ListingFreshnessReminder` (dos etapas) y `ListingExpired` con `failed()` → evento `reminder_failed`; tema de correo `avytra`.
- `ConfirmationLink` (firma temporal, validación sobre la URL canónica) y página autenticada `/panel/publicaciones/{listing}/confirmar` con limitador `confirmation`; botón "Sigue disponible" visible en el panel; resumen operativo real en `/admin`, filtros de caducadas y avisos fallidos, reenvío desde el detalle. Detalle en `CHANGELOG.md` y `docs/13`.
- Verificado en el navegador integrado con el usuario superadmin sembrado: email de primer aviso con el tema de marca; enlace firmado → login → aterrizaje en la página de confirmación (`intended`); clic en "Sí, sigue disponible" → "Gracias, tu publicación sigue vigente hasta el …"; resumen operativo con contadores y listas; detalle admin; móvil sin scroll horizontal; sin errores de consola propios.

### Phase 8 — Administración y asistencia (2026-09-26)
- `UserPolicy`; Actions `CreateAssistedUser`, `UpdateUserByAdmin`, `SendSetPasswordLink`; notificación `SetPasswordInvitation`; `AdminUserForm`; páginas `/admin/usuarios`, `/admin/usuarios/{user}` y `/admin/auditoria`; catálogo `AuditActions`; paleta de búsqueda Ctrl/Cmd+K; tarjeta "Cuentas asistidas" en el resumen; preselección de propietario (`?propietario=ID`) en el formulario de empresa y el wizard. Corrección del wizard: el paso 5 se rellena con la ubicación y el perfil online de una empresa existente. Detalle en `CHANGELOG.md`.
- Verificado en el navegador integrado con el superadmin sembrado: alta de una cuenta asistida desde el modal (validación de email obligatorio sin buzón de soporte, creación con email, redirección al detalle con toast y rastro de auditoría con las dos entradas), formulario de empresa con el propietario preseleccionado, página de auditoría con enlaces y cambios desplegables, paleta Ctrl+K con resultados. Sin errores de consola propios.

### Phase 9 — SEO (2026-09-26)
- `Sitemap` + `SitemapController` (`/sitemap.xml`, caché olvidada en cada transición y cambio de URL), `RobotsController` (`/robots.txt` con `app.url`), middleware `explore-redirects` (301 de `?sector=`/`?provincia=`), `ItemList` + `BreadcrumbList` en páginas de sector/provincia/online, `Organization` en la home, `og:image:width/height` y `twitter:*`, descripciones de páginas legales y de provincia, `noindex` en panel/admin/auth, textos reales de sectores en `CategorySeeder`. Detalle en `CHANGELOG.md` y `docs/15`.
- Verificado en el navegador integrado: `/robots.txt` y `/sitemap.xml` servidos por ruta; `/empresas?sector=…&tipo=online` redirige 301 a `/empresas/categoria/…?tipo=online`; página de sector con descripción real, un `ItemList` y un `BreadcrumbList` en `<body>` y ninguno en `<head>`.

### Phase 11 — Formulario de contacto relay (2026-09-26)
- `contact_requests` + `ContactRequest` + `ContactRequestPolicy`; Actions `SubmitContactRequest` y `MarkContactRequestAsRead`; notificación `ContactRequestReceived` (bajo demanda a `contact_email` o al email de la cuenta, `Reply-To` del interesado, fallo registrado); "Enviar mensaje" en la ficha en lugar de "Mostrar email" con honeypot, tiempo mínimo, limitador `contact-request` y tope diario; `/panel/mensajes` con contador en la barra lateral y aviso en el inicio; tarjeta admin y contador de no entregados; `IpHash`; ADR-019. Detalle en `CHANGELOG.md` y `docs/12`.
- Verificado en el navegador integrado con el usuario sembrado: ficha con "Enviar mensaje" y sin "Mostrar email" ni email en el HTML; modal con nombre y email prerrellenados; envío → toast "Mensaje enviado"; email en el log con `Reply-To: Test User <test@example.com>` tras `queue:work`; `/panel/mensajes` con el mensaje, badge "Nuevo" y contador "1" en la barra lateral; al abrirlo se marca leído y el contador desaparece; `mailto:` de respuesta con asunto; tarjeta "Mensajes recibidos" en `/admin/publicaciones/1` y "Mensajes no entregados" en `/admin`; móvil sin scroll horizontal (la lista se oculta al abrir un mensaje). Sin errores de consola.

### Phase 10 — Endurecimiento y lanzamiento (2026-09-26, código)
- Middlewares `AddSecurityHeaders` y `ThrottleRegistration`; honeypot y tiempo mínimo en el registro; `email:rfc,dns` en producción; `SchedulerHeartbeat` con aviso en el resumen operativo; aviso de 2FA al superadmin; página `errors/503`; Blaze opt-in; auditorías en CI; `docs/23-deployment.md`; ADR-018. Detalle en `CHANGELOG.md`.
- Verificado en el navegador integrado: página de mantenimiento real (`artisan down/up`), avisos del resumen admin, cabeceras de seguridad en la respuesta, pasada de accesibilidad en la home.

## In progress

- Nada de código. Phase 11 espera la revisión del propietario; el lanzamiento, las tareas del checklist.

## Next

- Revisión del propietario de Phase 11. Para probar en local: abrir una ficha publicada → "Enviar mensaje"; con `QUEUE_CONNECTION=database` el email sale con `php artisan queue:work` y con `MAIL_MAILER=log` queda en `storage/logs/laravel.log`; `/panel/mensajes` con la cuenta propietaria; `/admin/publicaciones/{id}` y `/admin`. El mensaje de prueba enviado durante la verificación (de `test@example.com` a la publicación 1) puede borrarse de `contact_requests`.
- Siguiente fase: elegir la próxima mejora de `docs/21` (candidatas naturales: estadísticas del anuncio, que ya cuenta con `contact_requests`; favoritos; verificación de empresas).
- Propietario: textos legales, proveedor de email y prueba en clientes reales, servidor de staging con `docs/23` (worker, cron, `APP_URL`, `AVYTRA_LOCATION_SALT`, `AVYTRA_SUPPORT_EMAIL`), smoke test y despliegue. Tras el lanzamiento, cada mejora del roadmap (`docs/21`) se convierte en una fase numerada.
- Revisión del propietario de Phase 9. Validación manual pendiente con la prueba de resultados enriquecidos de Google (ficha, sector y home) cuando el sitio esté en un dominio público; en local: `/robots.txt`, `/sitemap.xml`, `/empresas/categoria/hosteleria-y-restauracion` (ver el JSON-LD al final del `<body>`). Las categorías locales ya se re-sembraron con las descripciones (`php artisan db:seed --class=CategorySeeder`).
- Revisión del propietario de Phase 8. Para probar en local: `/admin/usuarios` → "Nuevo usuario" (sin `AVYTRA_SUPPORT_EMAIL` el email es obligatorio; con él, dejarlo vacío crea el alias `local+nombre@dominio`), luego "Nueva empresa para este usuario" y "Nueva publicación para este usuario"; `/admin/auditoria` para ver el rastro; Ctrl+K desde cualquier página admin. El email de contraseña sale por la cola (`php artisan queue:work`) al log con `MAIL_MAILER=log`. La cuenta "Prueba Asistida" (`prueba.asistida@example.com`) se creó durante la verificación y puede borrarse.
- Revisión del propietario de Phase 7. Para probar en local: `php artisan avytra:listings:process-freshness --dry-run` (lista lo que haría), `php artisan schedule:list`, y con `MAIL_MAILER=log` los emails quedan en `storage/logs/laravel.log`. Para ver un aviso real: poner `last_confirmed_at` de una publicación 45 días atrás, ejecutar el comando sin `--dry-run` con un worker de cola (`php artisan queue:work`) y abrir el enlace del email. La publicación de demo se confirmó durante la verificación (día 0 otra vez).
- Pendiente de revisión visual del propietario desde Phase 6: paso 7 del wizard y sección de imágenes del formulario de empresa (subida, reordenación, alt, borrado; requiere worker de cola).
## Blockers

- Ninguno en código. El lanzamiento espera las tareas del propietario del checklist.
- Notas aceptadas de Phase 11:
  - "Enviar mensaje" aparece en toda publicación `published` aunque no tenga `contact_email`: el buzón de reserva es el email de la cuenta del propietario (`Listing::contactInboxEmail()`), igual que en los recordatorios. En vendidas o pausadas la acción responde 404.
  - El remitente nunca recibe correo (ni copia ni confirmación): evita usar el relay para escribir a terceros. Su email se muestra al vendedor en el panel y como `Reply-To`.
  - La notificación es bajo demanda (`Notification::route('mail', …)`) porque el buzón puede no ser un usuario; `Notification::fake()` la comprueba con `assertSentOnDemand`. El fallo de entrega se marca en la fila (`delivery_failed_at`), no en `listing_events`.
  - Los tests que comparan textos con plural usan `trans_choice()` con la misma clave que la vista; `__()` sobre una mitad de la clave no encuentra la traducción.
  - Observación fuera de alcance: el pie de `flux:pagination` ("Showing 1 to 1 of 1 results") sale en inglés en todas las páginas paginadas del panel, no solo en mensajes.
- Notas aceptadas de Phase 10:
  - Blaze solo compila `listing-card`, `price`, `freshness-badge`, `listing-status-badge` y `empty-state`: compilar toda la carpeta de componentes rompía `auth-header` (props sin valor por defecto con una variable homónima en el ámbito del padre). Tras `view:clear`, la primera petición puede fallar una vez en Windows mientras Blaze escribe la vista compilada.
  - El limitador de registro va en un middleware del grupo `web` porque modificar la ruta de Fortify al arrancar no funciona (las búsquedas por nombre se refrescan después) ni sobreviviría a `route:cache`.
  - HSTS solo sobre HTTPS y en `APP_ENV=production`; `email:rfc,dns` también solo en producción (la suite no tiene red).
  - El aviso de 2FA no bloquea al superadmin: es política, no middleware (docs/16).
- Notas aceptadas de Phase 9:
  - El sitemap es un único archivo (hasta 50.000 URL); el índice de sitemaps queda para cuando haga falta. `lastmod` de las fichas = `sold_at` o `updated_at`.
  - `robots.txt` y el `Sitemap:` usan `config('app.url')`, no el host de la petición: `APP_URL` debe ser el dominio público en producción.
  - El 301 de `?sector=`/`?provincia=` solo actúa en la carga completa de `/empresas` con uno de los dos (no ambos); al cambiar el filtro en página Livewire actualiza la URL sin petición, y el canonical sigue apuntando a la página propia.
  - Las notas de Phase 4 sobre `robots.txt`, sitemap, `ItemList` y el 301 de `?sector=` quedan resueltas.
- Notas aceptadas de Phase 8:
  - Sin `AVYTRA_SUPPORT_EMAIL` no se pueden crear cuentas sin email (el formulario lo exige y lo explica). El alias `local+slug@dominio` recibe sufijo numérico si ya existe; el email de "establece tu contraseña" no se envía a alias.
  - `UserPolicy::changeRole` devuelve `false` antes de `before()`: ni el superadmin cambia roles desde la UI; solo `avytra:superadmin`.
  - La paleta Ctrl+K vive en la barra lateral del área admin como componente Livewire (`admin.command-palette`); Alpine solo asocia `cmd` a la tecla meta, así que se escuchan `meta.k` y `ctrl.k` por separado. Los resultados navegan con `redirectRoute(..., navigate: true)`.
  - Las etiquetas del audit log se resuelven en `AuditActions::label()`; una acción desconocida se muestra con su clave.
- Notas aceptadas de Phase 7:
  - Cada ejecución del comando envía como máximo un email por publicación: entre el segundo umbral y la pausa solo se manda el segundo aviso (marcando ambos flags); por encima de la pausa se pausa sin avisos (docs/13). Tras un scheduler parado varios días, una publicación puede pasar a `expired` sin avisos previos: el email de pausa explica cómo reactivar.
  - Las notificaciones en cola no admiten `ShouldBeUnique`; las barreras contra duplicados son el flag escrito antes de encolar y `withoutOverlapping()`.
  - `ConfirmationLink::isValid()` recalcula la firma sobre `route('panel.listings.confirm', [...])`, no sobre la URL de la petición: funciona detrás de proxies y en las peticiones de Livewire (y en `Livewire::withQueryParams()` en tests). Con enlace caducado la página no ofrece el botón (403 si se invoca la acción) y remite al panel, donde el botón sigue disponible.
  - El `role` del usuario `test@example.com` puede perderse al recrear la base de datos local; se restaura con `php artisan avytra:superadmin test@example.com`.
  - En producción hacen falta el scheduler (`php artisan schedule:run` cada minuto) y un worker de cola para que salgan los emails.
- Notas aceptadas de Phase 6:
  - El original subido nunca se sirve: vive en el disco privado y solo se usa para (re)generar conversiones (`php artisan media-library:regenerate` si cambian los tamaños). En producción hace falta `php artisan storage:link` y un worker de cola.
  - `PublicImage::fromMedia()` devuelve `null` mientras falte alguna conversión pedida: las vistas públicas muestran el placeholder hasta que la cola termina; el componente del panel muestra "Procesando la imagen…" y hace `wire:poll.5s`.
  - Sin portada, la primera imagen de la galería hace de portada en tarjetas y ficha (pero no de `og:image`).
  - La galería se guarda al elegir cada archivo (no hay botón de guardar): el paso 7 del wizard no persiste nada al avanzar y el formulario de empresa muestra la sección de imágenes fuera del `<form>`, solo al editar.
  - `config/media-library.php` mantiene `temporary_upload_model` como cadena (Media Library Pro no está instalado) para que Larastan analice `config/`.
  - `phpunit.xml` fija `memory_limit=1G`: las conversiones con GD a lo largo de toda la suite agotan los 128M del CLI.
  - Notas de Phase 5 que siguen vigentes: `Geocoder::isAvailable()`; `LocationPickerComponent` como clase base; montaje del mapa por `x-data`; borrar `public/hot` si queda huérfano.
  - Notas de Phase 4 que siguen vigentes: rutas del panel `panel.listings.*`; `tipo`/`operacion` en explorar; `robots.txt`, sitemap, `ItemList` y 301 de `?sector=` en Phase 9; `MarketplaceAggregates` cachea arrays planos; el compilador single-file no admite `#[Layout]` delante de `new class`; `throttle:public` no cubre `/livewire/update`.
  - Notas de Phase 3 que siguen vigentes: `title`/`slug` nullables hasta publicar; el limitador `register` se aplica en Phase 10 (`ExpireListing` notifica desde Phase 7).

## Important decisions

Ver `docs/DECISIONS.md` (ADR-001…017; ADR-006 aceptado con resultado). Decisiones menores de Phase 6 en las notas de "Blockers".

## Last tests executed

- 2026-09-26 — `composer test` (Pint + Larastan nivel 7 + Pest): **527 tests, todo en verde** (501 de Phase 10 más 26 nuevos). Nuevos: `Contact/*` (4 archivos) y `Policies/ContactRequestPolicyTest`.
- 2026-09-26 — Phase 10: 501 tests en verde.

## Last updated

2026-09-26 — Phase 11 implementada y verificada; pendiente de revisión del propietario. Lanzamiento (Phase 10) pendiente del checklist.
