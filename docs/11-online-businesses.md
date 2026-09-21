# 11 — Negocios online e híbridos

## Principio

Un negocio online no tiene puerta a la calle: su "ubicación" es su dominio, su plataforma y sus métricas. El modelo separa estos datos en `online_profiles` (1:1 con `businesses`) para que:

- los negocios físicos no arrastren columnas vacías;
- el wizard muestre solo lo relevante;
- el tipo `hybrid` combine `locations` + `online_profiles` sin duplicar nada.

## Tipos

| `BusinessType` | `locations` | `online_profiles` | Ejemplos |
|---|---|---|---|
| `physical` | obligatoria | no | restaurante, taller, comercio, peluquería, fábrica, almacén, academia, clínica |
| `online` | no | obligatorio | ecommerce, SaaS, marketplace, medio digital, afiliación, app, suscripción |
| `hybrid` | obligatoria | obligatorio | ecommerce con tienda física, academia con campus y formación online |

En listados, un negocio `online` muestra "Online" donde otros muestran la ubicación; un `hybrid` muestra la ubicación pública y un badge "Online + físico".

## Campos de `online_profiles`

| Campo | Oblig. | Visib. | Control | Notas |
|---|---|---|---|---|
| Tipo de negocio online (`online_business_type`) | **OB** | PUB | `flux:radio.group variant="cards"` | ecommerce, SaaS, marketplace, contenido, afiliación, app, servicio, otro |
| Plataforma tecnológica (`technology_platform`, `_other`) | REC | PUB | `flux:select` | Shopify, WooCommerce, PrestaShop, Magento, Laravel/custom, WordPress, otra |
| Web (vive en `businesses.website_url` + `website_visibility`) | REC | CFG | `flux:input type=url` + `flux:switch` "Mostrar públicamente" | Por defecto **privada**. Muchos vendedores no quieren que competidores o empleados sepan que está en venta. |
| Año de registro del dominio (`domain_registered_year`) | OPC | PUB | `flux:input type=number` | Antigüedad = calculada |
| Visitas mensuales (`monthly_visits` + `monthly_visits_disclosure`) | REC | CFG | `flux:input` + selector divulgación (exacto / rango / consultar / oculto) | Es la métrica que más valoran los compradores de online |
| Usuarios registrados (`registered_users`) | OPC | PUB | número | Se muestra solo si se rellena |
| Clientes activos (`active_customers`) | OPC | PUB | número | |
| Pedidos mensuales (`monthly_orders`) | OPC | PUB | número | ecommerce/marketplace |
| MRR | OPC | CFG | métrica financiera `monthly_recurring_revenue` en `listing_financial_metrics` | SaaS/suscripción; con divulgación como el resto de cifras |
| % ingresos recurrentes (`recurring_revenue_percent`) | OPC | PUB | `flux:input` 0–100 | |
| Canales de adquisición (`acquisition_channels`) | OPC | PUB | `flux:pillbox` | enum `AcquisitionChannel`: seo, sem, social_organic, social_ads, email, marketplaces, affiliates, referrals, offline, other |
| Redes sociales (`social_profiles`) | OPC | CFG (misma visibilidad que la web) | repetible {red, url} | Revelan la marca; heredan `website_visibility` |
| Marketplaces donde vende (`sells_on_marketplaces`) | OPC | PUB | `flux:pillbox` con opciones libres | Amazon, Etsy, eBay, Miravia… |
| Stock (`has_stock`) | OPC | PUB | `flux:switch` | Si sí, el valor del stock va a métricas financieras |
| Logística (`logistics_type`) | OPC | PUB | `flux:select` | propia, externa (3PL), dropshipping, no aplica |
| Equipo incluido (`team_included`) | OPC | PUB | `flux:switch` | Complementa `listings.includes_staff` — para online se pregunta aquí y se copia al listing al publicar para no duplicar preguntas |
| Propiedad intelectual incluida | — | PUB | en `listings.includes_intellectual_property` | Para online se pregunta en el paso 5 en lugar del 3 |

Todo lo que no es obligatorio se muestra bajo "Añadir más datos del negocio online" en el wizard, salvo tipo, plataforma, web y visitas.

## Métricas financieras específicas online

Se reutiliza `listing_financial_metrics` con `metric = monthly_recurring_revenue`. No se crean columnas nuevas. El wizard ofrece MRR solo cuando `online_business_type` ∈ {`saas`, `app`, `content`, `service`} o `recurring_revenue_percent > 0`.

## Presentación pública

Sección "Negocio online" en la ficha con:

- tipo y plataforma como badges;
- tabla de métricas presentes (visitas según divulgación, usuarios, clientes, pedidos, % recurrente);
- canales de adquisición como chips;
- marketplaces como chips;
- logística y stock;
- web y redes **solo si `website_visibility = public`**; si no, texto "La web se facilita al contactar".

## Validación al publicar

- `online`/`hybrid`: `online_business_type` obligatorio.
- `physical`: no debe existir `online_profile` (si el usuario cambia el tipo de híbrido a físico, el perfil se conserva en BD pero se ignora; la UI avisa de que se eliminará al guardar — decisión: **se elimina** para no arrastrar datos ocultos).
- URL válida y con esquema `https://` o `http://`; se normaliza.

## Privacidad específica

- La web y las redes sociales identifican inequívocamente el negocio: privadas por defecto.
- Las visitas pueden ser sensibles: divulgación configurable como las cifras económicas.
- No se hace scraping ni verificación automática de la web en el MVP (posible "verificación de dominio" en roadmap).
