# 05 — Campos de empresa y publicación

Leyenda de obligatoriedad: **OB** obligatorio para publicar · **REC** recomendado (se sugiere, no bloquea) · **OPC** opcional.
Leyenda de visibilidad: **PUB** público · **PRIV** privado (solo propietario y superadmin) · **CFG** configurable por el vendedor.

Principio: el wizard valida por paso lo mínimo; la validación completa "listo para publicar" se ejecuta al publicar y muestra qué falta. Un borrador puede estar tan incompleto como se quiera.

## Business (empresa)

### Identificación

| Campo | Columna | Oblig. | Visib. | Notas |
|---|---|---|---|---|
| Nombre comercial | `name` | OB | PUB | Puede ser genérico ("Panadería en Castellón centro") si el vendedor no quiere revelar la marca. La UI lo explica. |
| Razón social | `legal_name` | OPC | PRIV | Nunca pública en el MVP. |
| Tipo de negocio | `business_type` | OB | PUB | `physical` / `online` / `hybrid`. |
| Sector | `category_id` | OB | PUB | Catálogo. |
| Subsector | `subcategory_id` | REC | PUB | Catálogo, hijo del sector. |
| Descripción corta | `tagline` | REC | PUB | Máx. 160 caracteres. Se usa en tarjetas y meta description por defecto. |
| Descripción completa | `description` | OB | PUB | Texto plano con párrafos. Mín. 200 caracteres para publicar. |
| Año de inicio | `founded_year` | REC | PUB | "Años en funcionamiento" se calcula, no se almacena. |
| Forma jurídica | `legal_form` | OPC | CFG (`show_legal_form`, default false) | Enum. |
| Empleados | `employee_range` | REC | PUB | Rango, no número exacto: reduce fricción y protege privacidad. |
| Web | `website_url` | OPC | CFG (`website_visibility`: `public`/`private`) | Para online suele ser la propia tienda; muchos vendedores no quieren revelarla hasta hablar. |
| Logo | media `logo` | OPC | PUB | |
| Imagen principal | media `cover` | REC | PUB | Sin portada se muestra un placeholder de marca. Fuertemente recomendada. |
| Galería | media `gallery` | OPC | PUB | Máx. 12 imágenes en MVP. |

### Ubicación (solo `physical` y `hybrid`) — tabla `locations`

| Campo | Oblig. | Visib. | Notas |
|---|---|---|---|
| País | OB | PUB | `ES` por defecto; sin selector en MVP. |
| Provincia | OB | PUB | Siempre pública (es el mínimo útil para filtrar). |
| Municipio | OB | CFG | Público salvo `location_visibility = hidden`. |
| Código postal | OPC | PRIV | Solo para uso interno/geocodificación. |
| Dirección | OPC | CFG | Pública solo con `exact`. |
| Coordenadas | REC | CFG | Se derivan las coordenadas públicas según visibilidad. |
| Visibilidad de ubicación | OB | — | Default `approximate`. |

Ver [10-location-and-maps.md](10-location-and-maps.md).

### Perfil online (solo `online` y `hybrid`) — tabla `online_profiles`

Ver [11-online-businesses.md](11-online-businesses.md). Solo `online_business_type` es obligatorio.

## Listing (publicación)

### Operación y presentación

| Campo | Columna | Oblig. | Visib. | Notas |
|---|---|---|---|---|
| Tipos de operación | pivote `listing_operation_types` | OB (≥1) | PUB | Enum multi-selección: el vendedor marca todo lo que ofrece (p. ej. venta completa y entrada de socio). |
| Operación principal | `primary_operation_type` | OB | PUB | Una de las seleccionadas; badge principal y título sugerido. Si solo hay una, se asigna sola. |
| Porcentaje ofrecido | `stake_percent` | OPC | PUB | Solo para `partial_sale`, `partner_entry`, `investor_search`. |
| Condiciones de la operación | `operation_notes` | OPC | PUB | Texto corto ("Se busca socio con perfil comercial", "Mínimo 30 %"). |
| Título | `title` | OB | PUB | Máx. 90 caracteres. Se sugiere automáticamente ("Traspaso de panadería en Castellón") y el usuario puede editarlo. |
| Slug | `slug` | auto | PUB | Derivado del título; único; con historial de redirecciones. |
| Motivo de la venta | `reason_for_sale` | REC | PUB | Texto corto. Aporta mucha confianza al comprador. |
| Puntos destacados | `highlights` | OPC | PUB | JSON, hasta 5 frases cortas. |
| Estado | `status` | auto | — | Ver lifecycle. |

### Qué se incluye

| Campo | Columna | Oblig. | Visib. |
|---|---|---|---|
| Stock incluido | `includes_stock` (bool) | OPC | PUB |
| Maquinaria/equipamiento incluido | `includes_equipment` (bool) | OPC | PUB |
| Inmueble incluido (propiedad) | `includes_property` (bool) | OPC | PUB |
| Equipo/plantilla se mantiene | `includes_staff` (bool) | OPC | PUB |
| Propiedad intelectual/marca incluida | `includes_intellectual_property` (bool) | OPC | PUB |
| Detalle de activos incluidos | `included_assets_notes` (texto) | OPC | PUB |
| Local en alquiler | `premises_is_rented` (bool) | OPC | PUB |

Estos booleanos son tri-estado en la práctica (`null` = no indicado) y se muestran solo cuando se han indicado.

### Precio

| Campo | Columna | Oblig. | Visib. |
|---|---|---|---|
| Modo de precio | `price_disclosure` | OB | — (`exact`, `range`, `on_request`) |
| Precio solicitado | `asking_price` | OB si `exact` | PUB |
| Precio mínimo/máximo | `asking_price_min`, `asking_price_max` | OB si `range` | PUB |
| Negociable | `is_price_negotiable` (bool) | OPC | PUB |
| Moneda | `currency` | auto `EUR` | PUB |

"Consultar" (`on_request`) es legítimo y frecuente en este mercado; no se penaliza en el listado, pero las publicaciones con precio se ordenan primero cuando el usuario ordena por precio.

### Información económica — tabla `listing_financial_metrics`

Todas opcionales. Cada una con divulgación `exact` / `range` / `on_request` / `hidden`.

| Métrica | Valor real para el comprador | Riesgo de abandono | Recomendación en wizard |
|---|---|---|---|
| Facturación anual | Muy alto | Bajo (dato que todo vendedor conoce) | REC, primera del paso |
| Beneficio anual aproximado | Muy alto | Medio | REC |
| EBITDA | Alto (compradores profesionales) | Alto (muchos no saben qué es) | OPC, en sección "Más detalle" plegada |
| Ingresos mensuales | Medio (redundante con facturación) | Bajo | OPC, plegado |
| MRR | Alto solo para SaaS/suscripción | — | OPC, solo se ofrece a `online`/`hybrid` |
| Valor del stock | Medio | Bajo | OPC, solo si `includes_stock` |
| Alquiler mensual | Alto para físicos | Bajo | REC solo si `premises_is_rented` |
| Gastos mensuales aproximados | Medio | Medio | OPC, plegado |

El wizard muestra por defecto solo facturación, beneficio y (si procede) alquiler; el resto bajo "Añadir más datos económicos". Así el formulario no parece una auditoría.

### Contacto

Ver [12-contact-system.md](12-contact-system.md). Obligatorio: `preferred_contact_method` y al menos un canal relleno coherente con la preferencia.

### Vigencia y ciclo de vida (automáticos)

`published_at`, `last_confirmed_at`, `next_confirmation_at`, `first_reminder_sent_at`, `second_reminder_sent_at`, `paused_at`, `expired_at`, `sold_at`, `archived_at`, `suspended_at`, `suspension_reason`. Ver [06-listing-lifecycle.md](06-listing-lifecycle.md) y [13-freshness-and-notifications.md](13-freshness-and-notifications.md).

## Resumen de obligatorios para publicar

Empresa: nombre, tipo, sector, descripción (≥200 caracteres). Físico/híbrido: provincia y municipio. Online/híbrido: tipo de negocio online.
Publicación: al menos un tipo de operación (con operación principal), título, modo de precio (y valores coherentes), método de contacto preferido con su canal.

Todo lo demás es recomendado u opcional. Doce campos obligatorios en total; el resto de la ficha se enriquece progresivamente.

## Campos que se han descartado a propósito

- **Número exacto de empleados**: sustituido por rango.
- **Años en funcionamiento**: calculado desde `founded_year`.
- **Cesión** como tipo de operación: se cubre con `transfer`.
- **Ingresos mensuales como campo destacado**: redundante con facturación anual; se mantiene como métrica opcional.
- **Campos de valoración o multiplicadores**: fuera de alcance (ver roadmap).
