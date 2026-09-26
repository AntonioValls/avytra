# 23 — Despliegue y operación

Guía de puesta en producción y operación diaria de AVYTRA (Phase 10). Complementa [19-technical-architecture.md](19-technical-architecture.md) (colas, scheduler, caché) y [16-security-and-privacy.md](16-security-and-privacy.md).

## Requisitos del servidor

- PHP 8.4 con extensiones `pdo_mysql`, `mbstring`, `intl`, `gd` (conversiones WebP), `bcmath`, `ctype`, `fileinfo`, `openssl`, `tokenizer`, `xml`, `zip`.
- MySQL 8 / MariaDB 10.6+ (InnoDB; `config/database.php` fuerza `engine = InnoDB`).
- Node 22 solo para construir los assets (`npm run build`); no hace falta en tiempo de ejecución.
- Servidor web (Nginx o Caddy) apuntando a `public/`, HTTPS obligatorio (HSTS se envía solo sobre HTTPS en `APP_ENV=production`).
- Un proceso supervisado para la cola y una entrada de cron para el scheduler (abajo).
- Credenciales de Flux Pro en `auth.json` **fuera del repositorio** (o `composer config http-basic.composer.fluxui.dev ...` en el entorno de build).

## Variables de entorno relevantes

| Variable | Producción |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | Dominio público con `https://` (lo usan `robots.txt`, el sitemap, los enlaces de los emails y los enlaces firmados) |
| `APP_KEY` | Generada con `php artisan key:generate`; al rotar, la anterior va a `APP_PREVIOUS_KEYS` |
| `AVYTRA_LOCATION_SALT` | Cadena aleatoria y estable: determina el desplazamiento de las ubicaciones aproximadas. **No cambiarla** tras el lanzamiento (movería todos los puntos públicos) |
| `AVYTRA_SUPPORT_EMAIL` / `AVYTRA_SUPPORT_PHONE` | Buzón y teléfono de soporte. El email además permite crear cuentas asistidas sin email (alias `+`) |
| `MAIL_*` | Proveedor transaccional real (SMTP, Postmark, SES…). `MAIL_FROM_ADDRESS` en el dominio propio con SPF/DKIM |
| `QUEUE_CONNECTION` | `database` (por defecto) o `redis` |
| `CACHE_STORE` / `SESSION_DRIVER` | `database` funciona; `redis` si está disponible |
| `MEDIA_ORIGINALS_DISK` / `MEDIA_DISK` | `local` (privado) y `public` por defecto; un disco S3-compatible para las conversiones si el hosting no tiene disco persistente |
| `MEDIA_QUEUE_CONVERSIONS` | `true` (requiere worker) |
| `GEOCODING_DRIVER` | `null` salvo volumen bajo con `nominatim` y `NOMINATIM_EMAIL` real |
| `MAP_STYLE_URL` | Estilo MapLibre; el de OpenFreeMap por defecto no requiere clave |
| `LOG_CHANNEL` / `LOG_LEVEL` | `daily` (rotación) y `warning` o `error` |

## Primer despliegue

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate --force          # solo la primera vez
php artisan migrate --force
php artisan db:seed --class=CategorySeeder --force
php artisan avytra:geography:import       # catálogo de provincias y municipios (ver comando)
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan avytra:superadmin correo@dominio   # la cuenta debe existir (registro normal) y activar 2FA
```

Comprobaciones: `php artisan about`, `GET /up` responde 200, `/robots.txt` muestra el `Sitemap:` con el dominio público, `/sitemap.xml` se sirve.

## Despliegues posteriores

```bash
php artisan down --render="errors::503" --retry=60
git pull && composer install --no-dev --optimize-autoloader && npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan queue:restart
php artisan up
```

`errors::503` es la página de mantenimiento de marca (`resources/views/errors/503.blade.php`), autónoma: no depende de Vite, Livewire ni de la base de datos.

## Procesos permanentes

**Worker de cola** (Supervisor, systemd o el gestor del hosting), reiniciado en cada despliegue con `queue:restart`:

```bash
php artisan queue:work --tries=3 --backoff=60 --max-time=3600 --sleep=3
```

Por la cola pasan: emails (publicación, avisos de vigencia, pausa, invitación de contraseña, reportes, restablecimiento) y conversiones de imágenes. Sin worker, los emails no salen y las imágenes se quedan en "Procesando…".

**Scheduler**, una entrada de cron cada minuto:

```cron
* * * * * cd /ruta/avytra && php artisan schedule:run >> /dev/null 2>&1
```

Ejecuta cada hora `avytra:listings:process-freshness` (avisos y pausa automática) y cada minuto el latido `avytra:scheduler-heartbeat`. Si el latido se queda viejo (`avytra.monitoring.scheduler_stale_minutes`, 10 min), el resumen operativo de `/admin` lo avisa en rojo.

## Copias de seguridad

- Base de datos: `mysqldump --single-transaction avytra | gzip` diario, retención de 30 días, fuera del servidor.
- Archivos: `storage/app` completo (originales privados en `storage/app/private`, conversiones en `storage/app/public`). Si las conversiones van a S3, basta con el versionado del bucket; los originales siguen en `storage/app/private`.
- Las conversiones se pueden regenerar desde los originales con `php artisan media-library:regenerate`; los originales no se pueden regenerar.
- Restaurar: importar el dump, copiar `storage/app`, `php artisan storage:link`, limpiar cachés.

## Logs y errores

- `storage/logs/laravel-YYYY-MM-DD.log` con `LOG_CHANNEL=daily`. Los fallos definitivos de emails quedan además en `failed_jobs` y en el historial de la publicación (`reminder_failed`), visibles en `/admin` ("Avisos no entregados").
- `php artisan queue:failed` lista los jobs fallidos; `queue:retry all` los reintenta.
- Salud: `GET /up` (Laravel) para el monitor externo; latido del scheduler en `/admin`.

## Seguridad en producción (resumen de docs/16)

- Cabeceras `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` y `Strict-Transport-Security` (middleware `AddSecurityHeaders`). Sin CSP estricta (ADR-018).
- Panel, admin y auth con `noindex` y `Disallow` en `robots.txt`.
- Registro con honeypot, tiempo mínimo y limitador `register` (5/hora por IP, middleware `ThrottleRegistration`); emails validados con `email:rfc,dns` en producción.
- Superadmin solo por comando y con 2FA (el resumen operativo lo recuerda mientras no esté activado).
- `composer audit` y `npm audit` en CI (`.github/workflows/tests.yml`).

## Smoke test tras cada despliegue (staging y producción)

1. Home, `/empresas`, una ficha pública, `/robots.txt`, `/sitemap.xml`, `/up` → 200.
2. Registro de una cuenta nueva → email de verificación recibido → verificación → panel.
3. Crear empresa con ubicación y subir una portada → la conversión aparece (worker) en la ficha.
4. Publicar desde el wizard → email "publicada" recibido → ficha indexable (sin `noindex`).
5. `php artisan avytra:listings:process-freshness --dry-run` sin errores; `/admin` sin aviso rojo del scheduler tras un minuto.
6. Confirmar disponibilidad desde el panel y desde el enlace de un email de aviso (forzar `last_confirmed_at` en staging).
7. Reportar una publicación como visitante → aparece en `/admin/reportes`.
8. `php artisan down` muestra la página de mantenimiento; `php artisan up` la retira.

## Pendiente del propietario antes del lanzamiento

- Textos legales definitivos (aviso legal, privacidad, cookies) sustituyendo las plantillas.
- Envío de los emails a clientes reales (Gmail, Outlook, Apple Mail) desde el proveedor de producción y revisión visual.
- Validación de resultados enriquecidos (ficha, sector y home) con la herramienta de Google una vez en el dominio público.
- Despliegue de prueba en staging siguiendo esta guía y el smoke test completo.
