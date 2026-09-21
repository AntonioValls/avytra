# 10 — Ubicación, privacidad de ubicación y mapas

## Objetivos

1. Un negocio físico o híbrido tiene una ubicación real, pero el vendedor decide cuánto se revela.
2. El mapa nunca finge que una ubicación aproximada es exacta.
3. La lógica no se acopla a ningún proveedor de mapas ni de geocodificación.
4. Sin costes fijos ni abuso de servicios gratuitos con políticas incompatibles con producción.
5. El MVP funciona **sin geocodificación**: catálogo de municipios + pin manual.

## Modelo de datos

Tabla `locations` (ver [07-database-design.md](07-database-design.md)). Dos juegos de coordenadas:

- **Privadas:** `latitude`, `longitude`, `address_line`, `postal_code`. Solo propietario y superadmin.
- **Públicas (derivadas):** `public_latitude`, `public_longitude`, `public_radius_m`. Se recalculan cada vez que cambian las privadas o la visibilidad, dentro del modelo/Action, nunca en la vista.

## Niveles de visibilidad (`LocationVisibility`)

| Valor | UI | Qué se muestra en texto | Qué se muestra en mapa | Coordenadas públicas |
|---|---|---|---|---|
| `exact` | "Dirección exacta" | Dirección, municipio, provincia | Pin exacto | = privadas, radio `null` |
| `approximate` (default) | "Zona aproximada" | Municipio, provincia | Círculo de ~700 m centrado en un punto desplazado | Punto desplazado determinista, radio 700 |
| `city_only` | "Solo municipio" | Municipio, provincia | Círculo grande sobre el centroide del municipio (radio según población, 1.5–5 km) | Centroide de `municipalities` |
| `hidden` | "No mostrar ubicación" | Solo provincia | Sin mapa | `null` |

### Cálculo del punto aproximado

Para `approximate`, se desplaza el punto real una distancia aleatoria entre 250 y 600 m en una dirección aleatoria, usando un generador **determinista sembrado con `location.id` y una sal de aplicación** (`config('avytra.location.jitter_salt')`). Consecuencias:

- El punto público es estable entre renderizados (no se puede triangular refrescando).
- El punto real siempre está dentro del círculo mostrado (radio 700 m > desplazamiento máximo).
- Cambiar la sal cambia todos los puntos públicos (útil si se sospecha filtración).

El JSON-LD y cualquier endpoint público usan exclusivamente `public_*`. Ver [16-security-and-privacy.md](16-security-and-privacy.md).

## Catálogo geográfico

`regions`, `provinces`, `municipalities` con centroides, cargados por seeder desde `database/data/spain/*.csv` (fuente a documentar en el propio seeder: INE para códigos y nombres; centroides desde un dataset abierto con licencia compatible, verificado en Phase 5). Consecuencias:

- Selects de provincia y municipio sin llamadas externas (`flux:select searchable` para provincia, `flux:autocomplete` para municipio filtrado por provincia).
- `city_only` y `hidden` no necesitan geocodificación.
- Filtro por provincia por FK, indexado.
- Nombres normalizados (evita "València"/"Valencia" duplicados).

## Cómo obtiene el vendedor sus coordenadas

Orden de preferencia en el wizard (paso 5):

1. Selecciona provincia y municipio → el mapa se centra en el centroide del municipio.
2. Opcionalmente escribe dirección y código postal (privados).
3. **Arrastra el pin** o hace clic en el mapa para colocar el punto real (`geocoding_source = manual_pin`).
4. Si no coloca pin, se usa el centroide del municipio (`geocoding_source = municipality_centroid`) y se fuerza `city_only` como visibilidad efectiva (no tiene sentido "aproximada" sin punto real). La UI lo explica.
5. Botón "Buscar dirección en el mapa" (geocodificación) — **mejora opcional** dentro de Phase 5, detrás de la interfaz `Geocoder`; si el proveedor no está configurado, el botón no aparece.

Esto hace que el MVP no dependa de ningún geocodificador.

## Tecnología del mapa

### Decisión: MapLibre GL JS + OpenFreeMap (estilo configurable)

Se valoraron:

| Opción | Pros | Contras |
|---|---|---|
| **MapLibre GL + OpenFreeMap** (usado en ParkingParaCamiones) | Sin API key, sin cuota, uso comercial permitido, vector tiles nítidos, clustering nativo, estilo cambiable por URL | Requiere WebGL; bundle ~250 KB gz; ParkingParaCamiones necesitó vendorizar el worker por incompatibilidad con Vite |
| Leaflet + tiles raster | Muy ligero, sin WebGL | No existe proveedor de tiles raster gratuito para producción comercial sin key (OSM prohíbe uso intensivo; CARTO/Stadia/MapTiler exigen plan o limitan). Coste o cuota inevitables |
| Google Maps | Familiar | Coste por carga, términos restrictivos sobre almacenamiento de coordenadas, dependencia |

Decisión (ADR-005): **MapLibre GL JS** con estilo por defecto de **OpenFreeMap** (`liberty`), URL en `config/avytra.php` → `map.style_url` (env `MAP_STYLE_URL`), de forma que cambiar a MapTiler, Stadia o un servidor propio es un cambio de configuración. Se muestra la atribución obligatoria. Se reconoce el riesgo de depender de un servicio comunitario sin SLA: si el volumen crece o el servicio degrada, se contrata MapTiler (compatible con MapLibre) sin tocar código.

Se **reutiliza conceptualmente** de ParkingParaCamiones (ver [22-parkingparacamiones-reference.md](22-parkingparacamiones-reference.md)):

- `map-support.js`: detección de WebGL2, `createMap()` con try/catch y `webglcontextlost`, fallback HTML servido desde el servidor.
- Patrón de desacoplo de Livewire: el mapa lee `data-*` y se inicializa en `DOMContentLoaded` y `livewire:navigated` con guarda `dataset.mapInitialized`.

Se **corrige** respecto a ParkingParaCamiones:

- Un único módulo `resources/js/map/` con dos funciones públicas (`mountListingMap(el)`, `mountExploreMap(el)`) y un componente Blade `<x-map.listing :location="..." />` que emite el HTML y el fallback.
- Colores y estilo desde config/tokens, no literales en JS.
- El problema del worker de MapLibre con Vite se verifica en Phase 5 con la versión actual de MapLibre y Vite 8; si persiste, se resuelve con un paso de build (copia con checksum) en lugar de archivos vendorizados a mano.
- Ningún endpoint público devuelve todas las coordenadas: el mapa de explorar solo recibe los puntos de la página actual, y solo `public_*`.

### Render según visibilidad

- `exact`: marcador (símbolo AVYTRA en Ink) + popup con dirección.
- `approximate` / `city_only`: capa `circle` con relleno Transfer Blue al 15 % y borde al 60 %, sin marcador; texto bajo el mapa "Ubicación aproximada. La dirección exacta se facilita al contactar."
- `hidden`: sin mapa; texto "Provincia de X".

Fallback sin WebGL: bloque con el texto de ubicación y enlace "Ver zona en OpenStreetMap" (solo para `exact`; para aproximadas, enlace al municipio).

## Geocodificación (opcional, abstraída)

```php
namespace App\Services\Geocoding;

interface Geocoder
{
    /** @return GeocodingResult|null */
    public function geocode(string $query, ?string $countryCode = 'ES'): ?GeocodingResult;
}

final readonly class GeocodingResult
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?string $formattedAddress,
        public ?string $postalCode,
        public string $provider,
        public ?float $confidence,
    ) {}
}
```

- Drivers previstos: `NullGeocoder` (por defecto; desactiva la función), `NominatimGeocoder` (solo desarrollo/volumen bajo: `User-Agent` identificativo, máximo 1 petición/segundo mediante `RateLimiter`, caché de resultados por consulta normalizada 30 días, solo disparado por acción explícita del usuario, nunca en bucle), y un driver de pago con key (Geoapify o LocationIQ, ambos con capa gratuita suficiente y términos comerciales claros) para producción si se decide activarla.
- Selección por `config('avytra.geocoding.driver')`.
- Resultado siempre revisable por el usuario en el mapa antes de guardar.
- No se geocodifica en masa ni en background sin consentimiento del usuario.

## Filtros geográficos

MVP: solo provincia (FK). Radio/distancia: fuera del MVP; si llega, prefiltro por bounding box sobre el índice `(public_latitude, public_longitude)` y Haversine en PHP o SQL sobre el subconjunto, nunca sobre coordenadas privadas.

## Tests previstos

- Derivación de `public_*` para cada visibilidad (unit).
- Determinismo del jitter y contención dentro del radio.
- La proyección pública nunca contiene `latitude`/`longitude`/`address_line` salvo `exact`.
- Componente de mapa emite fallback y `data-*` correctos según visibilidad (feature, HTML).
- Publicar físico sin provincia/municipio falla; online con ubicación falla.
