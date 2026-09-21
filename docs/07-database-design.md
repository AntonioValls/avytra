# 07 — Diseño de base de datos (nivel documental)

Motor: MySQL/MariaDB en local y producción; SQLite en memoria para tests (ya configurado). Evitar funciones específicas de motor (sin columnas espaciales, sin JSON functions avanzadas) para que los tests en SQLite sean fieles.

## Decisiones generales

- **IDs:** `bigint` autoincremental (`$table->id()`) en todas las tablas. No se usan UUID/ULID: los recursos públicos se exponen por `slug`, los privados están protegidos por Policies, y los IDs numéricos simplifican índices, joins y depuración. Ver ADR-003.
- **Importes:** `unsignedBigInteger` en euros sin decimales + `currency` `char(3)` default `EUR`.
- **Coordenadas:** `decimal(10,7)` para latitud y longitud (precisión ~1 cm). Índice compuesto en las coordenadas públicas.
- **Enums:** columnas `string` (no `enum` de MySQL) con cast a enum PHP. Facilita añadir valores sin migraciones `ALTER`.
- **Timestamps:** `timestamps()` en todo; `softDeletes()` solo en `businesses` y `listings` (permite recuperar borrados accidentales por el superadmin; el borrado físico de borradores sí es real).
- **JSON:** solo para listas simples sin necesidad de consulta (`highlights`, `acquisition_channels`).
- **Autoría:** `created_by_user_id`, `updated_by_user_id` nullable con `nullOnDelete()`.

## Tablas

### users (existente; se amplía)

```text
+ role                 string(20)  default 'user'   index
+ phone                string(30)  nullable
+ is_assisted          boolean     default false    (cuenta creada y gestionada por el superadmin en nombre de la persona)
```

Se conservan las columnas de Fortify (2FA, passkeys en tabla propia).

### categories

```text
id
parent_id              FK categories nullable, nullOnDelete
name                   string(80)
slug                   string(100) unique
description            text nullable            (contenido para página de categoría)
sort_order             unsignedSmallInteger default 0
is_active              boolean default true
timestamps
index (parent_id, sort_order)
```

### regions / provinces / municipalities

```text
regions:        id, country_code char(2) default 'ES', code string(5), name, slug unique, timestamps
provinces:      id, region_id FK, country_code, code string(5) (INE), name, slug unique, latitude, longitude, timestamps
municipalities: id, province_id FK, country_code, code string(10) (INE), name, slug, latitude, longitude, population nullable, timestamps
                unique (province_id, slug); index (province_id, name)
```

Datos cargados desde archivos en `database/data/` (CSV/JSON con fuente documentada), no desde arrays PHP.

### businesses

```text
id
owner_user_id          FK users, restrictOnDelete (decisión de cascada en Phase 2)
created_by_user_id     FK users nullable, nullOnDelete
updated_by_user_id     FK users nullable, nullOnDelete
business_type          string(20)                       index
category_id            FK categories, restrictOnDelete   index
subcategory_id         FK categories nullable, nullOnDelete
name                   string(120)
legal_name             string(160) nullable             (PRIVADO)
legal_form             string(30) nullable
show_legal_form        boolean default false
tagline                string(160) nullable
description            text nullable
founded_year           unsignedSmallInteger nullable
employee_range         string(30) nullable
website_url            string(255) nullable
website_visibility     string(10) default 'private'
timestamps, softDeletes
index (owner_user_id)
```

### locations

```text
id
business_id            FK businesses, cascadeOnDelete
is_primary             boolean default true
country_code           char(2) default 'ES'
province_id            FK provinces, restrictOnDelete   index
municipality_id        FK municipalities nullable, nullOnDelete  index
postal_code            string(10) nullable              (PRIVADO)
address_line           string(255) nullable             (PRIVADO salvo exact)
latitude               decimal(10,7) nullable           (PRIVADO)
longitude              decimal(10,7) nullable           (PRIVADO)
location_visibility    string(20) default 'approximate'
public_latitude        decimal(10,7) nullable           (derivado)
public_longitude       decimal(10,7) nullable           (derivado)
public_radius_m        unsignedInteger nullable         (derivado; null = pin exacto)
geocoding_source       string(30) nullable              manual_pin | geocoder | municipality_centroid
geocoding_provider     string(30) nullable
geocoded_at            timestamp nullable
timestamps
unique (business_id, is_primary) — en MVP una fila por empresa
index (public_latitude, public_longitude)
```

### online_profiles

```text
id
business_id            FK businesses, cascadeOnDelete, unique
online_business_type   string(30)
technology_platform    string(30) nullable
technology_platform_other string(80) nullable
domain_registered_year unsignedSmallInteger nullable
monthly_visits         unsignedInteger nullable
monthly_visits_disclosure string(15) default 'exact'
registered_users       unsignedInteger nullable
active_customers       unsignedInteger nullable
monthly_orders         unsignedInteger nullable
recurring_revenue_percent unsignedTinyInteger nullable
acquisition_channels   json nullable                    (lista de enum AcquisitionChannel)
social_profiles        json nullable                    (lista {network, url}, visibilidad = website_visibility)
sells_on_marketplaces  json nullable                    (lista de strings: Amazon, Etsy…)
has_stock              boolean nullable
logistics_type         string(20) nullable
team_included          boolean nullable
timestamps
```

### listings

```text
id
business_id            FK businesses, restrictOnDelete   index
created_by_user_id     FK users nullable
updated_by_user_id     FK users nullable
status                 string(20) default 'draft'        index
operation_type         string(30)                        index
title                  string(120)
slug                   string(140) unique
reason_for_sale        string(500) nullable
highlights             json nullable
includes_stock         boolean nullable
includes_equipment     boolean nullable
includes_property      boolean nullable
includes_staff         boolean nullable
includes_intellectual_property boolean nullable
included_assets_notes  text nullable
premises_is_rented     boolean nullable
price_disclosure       string(15) default 'on_request'
asking_price           unsignedBigInteger nullable       index
asking_price_min       unsignedBigInteger nullable
asking_price_max       unsignedBigInteger nullable
is_price_negotiable    boolean nullable
currency               char(3) default 'EUR'
-- contacto
contact_name           string(80) nullable
preferred_contact_method string(20) nullable
contact_email          string(255) nullable
contact_phone          string(30) nullable
contact_whatsapp       string(30) nullable
contact_website_url    string(255) nullable
contact_form_url       string(255) nullable
contact_other          string(255) nullable
contact_notes          string(255) nullable               ("Llamar de 9 a 14h")
-- ciclo de vida
published_at           timestamp nullable                index
last_confirmed_at      timestamp nullable                index
next_confirmation_at   timestamp nullable                index
first_reminder_sent_at timestamp nullable
second_reminder_sent_at timestamp nullable
paused_at, expired_at, sold_at, archived_at, suspended_at   timestamp nullable
suspension_reason      string(500) nullable
timestamps, softDeletes
index (status, published_at)
index (status, next_confirmation_at)
```

Nota: la restricción "una publicación no terminada por empresa" se aplica en el Action de creación (no con índice único, porque los estados terminales conviven).

### listing_financial_metrics

```text
id
listing_id             FK listings, cascadeOnDelete
metric                 string(40)
disclosure             string(15) default 'exact'
amount                 unsignedBigInteger nullable
amount_min             unsignedBigInteger nullable
amount_max             unsignedBigInteger nullable
currency               char(3) default 'EUR'
period_year            unsignedSmallInteger nullable
timestamps
unique (listing_id, metric)
```

### listing_events

```text
id
listing_id             FK listings, cascadeOnDelete   index
type                   string(30)
actor_user_id          FK users nullable, nullOnDelete
on_behalf_of_user_id   FK users nullable, nullOnDelete
payload                json nullable
created_at             timestamp
index (listing_id, type, created_at)
```

### listing_slug_redirects

```text
id
listing_id             FK listings, cascadeOnDelete
old_slug               string(140) unique
created_at
```

### listing_reports

```text
id
listing_id             FK listings, cascadeOnDelete   index
reporter_user_id       FK users nullable, nullOnDelete
reporter_email         string(255) nullable
reason                 string(30)
message                text nullable
status                 string(15) default 'open'      index
ip_hash                string(64) nullable            (sha256 de IP+salt, para rate limit y detectar abuso)
resolved_by_user_id    FK users nullable
resolved_at            timestamp nullable
resolution_notes       string(500) nullable
timestamps
```

### audit_logs

```text
id
actor_user_id          FK users nullable, nullOnDelete   index
on_behalf_of_user_id   FK users nullable, nullOnDelete
action                 string(60)                        (business.updated_by_admin, listing.suspended, business.owner_changed, user.created_by_admin…)
subject_type           string(120)
subject_id             unsignedBigInteger
changes                json nullable                     ({before:{}, after:{}} solo de campos relevantes)
ip_address             string(45) nullable
created_at
index (subject_type, subject_id)
index (action, created_at)
```

### media

Depende de la decisión de [17-media-strategy.md](17-media-strategy.md). Con Spatie Media Library la tabla `media` la crea el paquete (polimórfica, con `collection_name`, `order_column`, `custom_properties` para `alt`). Con solución propia: `business_images (id, business_id, collection, disk, path, width, height, size, alt, sort_order, timestamps)`.

### Tablas del framework ya existentes

`users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `passkeys`.

## Índices y consultas críticas

| Consulta | Índices que la sirven |
|---|---|
| Listado público (published, orden recientes) | `listings (status, published_at)` |
| Filtro por precio | `listings.asking_price` |
| Filtro por tipo/sector | `businesses.business_type`, `businesses.category_id` (join) |
| Filtro por provincia | `locations.province_id` (join) |
| Scheduler: pendientes de aviso/pausa | `listings (status, next_confirmation_at)`, `last_confirmed_at` |
| Ficha por slug | `listings.slug` unique, `listing_slug_redirects.old_slug` unique |
| Panel del usuario | `businesses.owner_user_id` |
| Búsqueda de texto | `LIKE` sobre `listings.title` y `businesses.name` en MVP; índice `FULLTEXT (title, reason_for_sale)` + `businesses (name, tagline, description)` cuando el volumen lo justifique (MySQL; en SQLite se degrada a LIKE en tests). |

## Preparado para el futuro sin implementarlo

- Multi-moneda: columna `currency` ya presente.
- Multi-país: `country_code` en catálogo geográfico y ubicaciones.
- Multi-sede: `locations.is_primary`.
- Monetización: nada en el esquema lo impide; una futura tabla `listing_promotions` o `plans` se relaciona por `listing_id` sin tocar lo existente.
- Verificación: `businesses.verified_at` cuando llegue.
- Búsqueda externa: los modelos son "searchables" triviales para Scout si algún día se necesita.
