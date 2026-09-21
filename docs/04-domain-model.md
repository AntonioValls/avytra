# 04 — Modelo de dominio

## Diagrama de entidades

```text
User 1 ──< Business 1 ──< Listing 1 ──< ListingFinancialMetric
                │                │
                │                ├──< ListingReport
                │                ├──< ListingSlugRedirect
                │                └──< ListingEvent (histórico de estado / vigencia)
                │
                ├── 1 Location (0..1 en MVP; 0..N en el futuro)
                ├── 1 OnlineProfile (0..1)
                ├──> Category (sector) y Category (subsector, opcional)
                └──< Media (logo, cover, gallery)

Region 1 ──< Province 1 ──< Municipality      (catálogo geográfico de España, seed)
Category (parent_id auto-referencial)          (catálogo de sectores, seed)
AuditLog (actor, subject polimórfico)          (trazabilidad administrativa)
```

## Entidades

### User

Ya existe (starter kit). Se añade:

- `role` (`UserRole` enum: `user`, `superadmin`), default `user`.
- `phone` opcional (solo para uso interno/asistencia; **nunca** se publica automáticamente).
- Relaciones: `businesses()` (hasMany por `owner_user_id`), `listings()` (hasManyThrough Business).

### Business — la empresa real

Representa el negocio, con independencia de si está o no en venta. Persiste después de una venta (histórico) y puede volver a publicarse.

- Pertenece a un `User` (`owner_user_id`).
- Tiene un `BusinessType` (`physical`, `online`, `hybrid`).
- Tiene una `Category` obligatoria (sector) y una subcategoría opcional.
- Tiene 0..1 `Location` (obligatoria para `physical` y `hybrid`, prohibida para `online`).
- Tiene 0..1 `OnlineProfile` (obligatorio para `online` y `hybrid`, prohibido para `physical`).
- Tiene media: logo (0..1), portada (0..1), galería (0..N).
- Tiene 0..N `Listing`. Como máximo **una publicación activa** a la vez (activa = `draft`, `published`, `paused`, `expired` o `suspended`; es decir, no `sold` ni `archived`). Invariante aplicada en el Action de creación.

Contiene datos **descriptivos y estables** del negocio: nombre comercial, razón social (privada), forma jurídica, año de fundación, empleados, descripción, web.

### Listing — la publicación

Representa el acto de ofrecer el negocio en el mercado: qué operación, a qué precio, qué incluye, cómo contactar y en qué estado está.

- Pertenece a un `Business`.
- Tiene `ListingStatus` (ver [06-listing-lifecycle.md](06-listing-lifecycle.md)).
- Tiene **uno o varios** `OperationType` (tabla pivote `listing_operation_types`) y un `primary_operation_type` que se usa en título sugerido, badge principal y ordenación. Ejemplo: una empresa puede ofrecer a la vez "venta completa" y "entrada de socio"; el comprador ve una única ficha con las opciones ofrecidas. Para operaciones parciales (`partial_sale`, `partner_entry`, `investor_search`) puede indicarse `stake_percent` (porcentaje ofrecido) y `operation_notes` (condiciones en texto corto).
- Tiene título y slug público únicos (el slug es la URL de la ficha).
- Contiene precio y su modo de divulgación, condiciones (qué se incluye), motivo de venta, puntos destacados.
- Contiene la configuración de contacto (`contact_*`, `preferred_contact_method`).
- Contiene los timestamps del ciclo de vida (`published_at`, `last_confirmed_at`, `next_confirmation_at`, `paused_at`, `expired_at`, `sold_at`, `archived_at`, `suspended_at`) y control de recordatorios.
- Tiene 0..N `ListingFinancialMetric` (facturación, beneficio, EBITDA, etc., cada una con su modo de divulgación).
- Tiene 0..N `ListingReport`, 0..N `ListingSlugRedirect`, 0..N `ListingEvent`.

**Por qué el contacto está en Listing y no en Business:** cada publicación puede llevar un contacto distinto (el propietario, un familiar, un gestor). Además, cuando el superadmin publica en nombre de una persona sin email, el contacto de la publicación es el teléfono de esa persona, no el email de la cuenta. Tener el contacto en la publicación evita mostrar datos del perfil de usuario por accidente.

**Por qué el precio y las cifras están en Listing:** cambian entre una venta y otra, y su divulgación es una decisión de la publicación.

**Por qué la ubicación está en Business:** el local está donde está. La *visibilidad* de esa ubicación se configura en `Location` (`location_visibility`) y se aplica al proyectar la publicación pública. Si en el futuro hiciera falta un override por publicación, se añadiría `listings.location_visibility_override` sin tocar el modelo.

### Location

Ubicación de un negocio físico o híbrido.

- `business_id`, `is_primary` (true; preparado para varias sedes).
- Catálogo: `country_code` (`ES` por defecto), `province_id`, `municipality_id`, `postal_code`.
- Dirección libre: `address_line` (privada por defecto).
- Coordenadas exactas: `latitude`, `longitude` (privadas).
- Coordenadas públicas precalculadas: `public_latitude`, `public_longitude`, `public_radius_m` (derivadas según visibilidad; ver [10-location-and-maps.md](10-location-and-maps.md)).
- `location_visibility` (`LocationVisibility` enum: `exact`, `approximate`, `city_only`, `hidden`). Default `approximate`.
- `geocoded_at`, `geocoding_provider`, `geocoding_source` (`manual_pin`, `geocoder`, `municipality_centroid`).

### OnlineProfile

Datos específicos de negocios online e híbridos. Ver [11-online-businesses.md](11-online-businesses.md).

### Category

Sector de actividad. Dos niveles (`parent_id`). Seed inicial de ~15 sectores y ~60 subsectores. Slug para URLs `/empresas/categoria/{slug}`.

### Region / Province / Municipality

Catálogo geográfico de España cargado por seeder (17 comunidades + 2 ciudades autónomas, 52 provincias, ~8.100 municipios con centroide). Permite selects sin geocodificación, filtros por provincia y páginas geográficas futuras. Preparado para otros países mediante `country_code`, sin implementarlos.

### Media

Logo, portada y galería de la empresa. Estrategia en [17-media-strategy.md](17-media-strategy.md).

### ListingFinancialMetric

Una fila por métrica económica declarada en la publicación:

- `metric` (`FinancialMetric` enum: `annual_revenue`, `annual_profit`, `ebitda`, `monthly_revenue`, `monthly_recurring_revenue`, `stock_value`, `monthly_rent`, `monthly_expenses`).
- `disclosure` (`Disclosure` enum: `exact`, `range`, `on_request`, `hidden`).
- `amount`, `amount_min`, `amount_max` (enteros en EUR), `currency` (`EUR`).
- `period_year` opcional (a qué ejercicio se refiere).

Evita 30 columnas en `listings` y permite añadir métricas sin migraciones. El precio solicitado **sí** vive como columnas en `listings` porque es filtrable y ordenable en el listado público.

### ListingEvent

Histórico de transiciones y acciones de vigencia de una publicación: `published`, `paused`, `resumed`, `confirmed`, `reminder_sent`, `expired`, `sold`, `archived`, `suspended`, `unsuspended`, `slug_changed`. Con `actor_user_id`, `on_behalf_of_user_id`, `payload` JSON. Es la fuente de "Disponibilidad confirmada hace N días" junto con `last_confirmed_at`, y evita duplicar recordatorios.

### ListingReport

Reporte público de una publicación. Ver [09-dashboard-and-admin.md](09-dashboard-and-admin.md).

### ListingSlugRedirect

Slugs antiguos → listing actual, para redirección 301. Ver [15-seo.md](15-seo.md).

### AuditLog

Registro de acciones administrativas y de cambios sensibles (cambio de propietario, suspensión, edición por superadmin de un recurso ajeno). Ver [16-security-and-privacy.md](16-security-and-privacy.md).

## Enums PHP (namespace `App\Enums`)

Todos son `string` backed enums con métodos `label(): string` (traducible), y cuando procede `color()`/`icon()` para badges Flux.

| Enum | Valores |
|---|---|
| `UserRole` | `user`, `superadmin` |
| `BusinessType` | `physical`, `online`, `hybrid` |
| `LegalForm` | `sole_trader` (autónomo), `sl`, `sa`, `slu`, `cooperative`, `community_of_goods` (comunidad de bienes), `other` |
| `EmployeeRange` | `none`, `one_to_two`, `three_to_five`, `six_to_ten`, `eleven_to_twenty_five`, `twenty_six_to_fifty`, `more_than_fifty` |
| `ListingStatus` | `draft`, `published`, `paused`, `expired`, `sold`, `archived`, `suspended` |
| `OperationType` | `full_sale`, `transfer` (traspaso), `share_sale` (venta de la sociedad completa), `partial_sale` (venta parcial de participaciones), `asset_sale`, `partner_entry`, `investor_search`, `other` |
| `PriceDisclosure` | `exact`, `range`, `on_request` |
| `Disclosure` | `exact`, `range`, `on_request`, `hidden` |
| `FinancialMetric` | ver arriba |
| `LocationVisibility` | `exact`, `approximate`, `city_only`, `hidden` |
| `ContactMethod` | `email`, `phone`, `whatsapp`, `website`, `external_form`, `other` |
| `OnlineBusinessType` | `ecommerce`, `saas`, `marketplace`, `content`, `affiliate`, `app`, `service`, `other` |
| `TechnologyPlatform` | `shopify`, `woocommerce`, `prestashop`, `magento`, `laravel_custom`, `wordpress`, `other` |
| `LogisticsType` | `own`, `outsourced`, `dropshipping`, `none` |
| `AcquisitionChannel` | `seo`, `sem`, `social_organic`, `social_ads`, `email`, `marketplaces`, `affiliates`, `referrals`, `offline`, `other` |
| `ReportReason` | `unavailable`, `false_information`, `duplicate`, `fraud_spam`, `inappropriate`, `other` |
| `ReportStatus` | `open`, `resolved`, `dismissed` |
| `ListingEventType` | ver arriba |
| `ReminderStage` | `first`, `second` |

Sobre la taxonomía de `OperationType`: se descarta `cession` (cesión) como valor propio porque en la práctica es un traspaso o una venta de activos; quien lo necesite usa `transfer` y lo matiza en la descripción. `share_sale` y `asset_sale` se mantienen separados porque el comprador los evalúa de forma muy distinta (asume la sociedad con su pasivo vs. compra solo activos). `partial_sale` (vender un porcentaje) se distingue de `partner_entry` (incorporar un socio que aporta capital y trabajo) y de `investor_search` (capital sin implicación operativa). Una publicación puede combinar varios valores (ADR-015); la invariante "una publicación no terminada por empresa" se mantiene.

## Invariantes del dominio

1. Un `Listing` pertenece a exactamente un `Business`; su propietario es el del `Business`.
2. Un `Business` tiene como máximo un `Listing` no terminado (`sold`/`archived` son terminales).
3. `physical`/`hybrid` ⇒ `Location` obligatoria para publicar; `online` ⇒ sin `Location`.
4. `online`/`hybrid` ⇒ `OnlineProfile` obligatorio para publicar; `physical` ⇒ sin `OnlineProfile`.
5. Para publicar, el `Listing` debe cumplir el conjunto de campos obligatorios de [05-business-fields.md](05-business-fields.md) (validación "ready to publish", distinta de la validación por paso del wizard).
6. Al publicar, `last_confirmed_at = now()` y `next_confirmation_at = now() + confirmation_period`.
7. Al confirmar disponibilidad, se actualizan `last_confirmed_at`, `next_confirmation_at` y se reinician los flags de recordatorio.
8. Solo `published` es visible públicamente; `sold` es visible con marca "Vendida" durante un período configurable y luego solo bajo URL directa con `noindex` (ver [15-seo.md](15-seo.md)).
9. Los datos públicos se obtienen **siempre** a través de la proyección pública del listing, nunca serializando el modelo completo.
10. Los importes se almacenan como enteros en EUR sin decimales con columna `currency` (preparado para futuro, sin conversión).

## Qué NO se modela en el MVP (a propósito)

- Favoritos, alertas, mensajes, ofertas.
- Verificación de empresas.
- Planes, pagos, destacados.
- Multi-ubicación en UI (el modelo lo admite).
- Multi-idioma de contenido (el contenido de usuario es monolingüe; las etiquetas de UI van por `lang/`).
