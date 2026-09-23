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

`regions`, `provinces`, `municipalities` con centroides y población, cargados por el comando `avytra:import-geography` (idempotente, upsert por código INE) desde `database/data/spain/*.csv`. Fuentes y procesado en `database/data/spain/README.md`: códigos, nombres y centroides del dataset `georef-spain-municipio` de Opendatasoft (derivado del Nomenclátor del IGN, CC BY 4.0); población de Wikidata (CC0). 19 comunidades, 52 provincias, 8.131 municipios. Consecuencias:

- Selects de provincia y municipio sin llamadas externas (`flux:select variant="listbox" searchable` en ambos: el municipio se filtra por provincia en servidor y se elige por id, lo que evita mapear texto a id como exigiría `flux:autocomplete`).
- `city_only` y `hidden` no necesitan geocodificación.
- Filtro por provincia por FK, indexado.
- Nombres normalizados (evita "València"/"Valencia" duplicados).

## Cómo obtiene el vendedor sus coordenadas

Orden de preferencia en el wizard (paso 5):

1. Selecciona provincia y municipio → el mapa se centra en el centroide del municipio.
2. Opcionalmente escribe dirección y código postal (privados).
3. **Arrastra el pin** o hace clic en el mapa para colocar el punto real (`geocoding_source = manual_pin`).
4. Si no coloca pin, se usa el centroide del municipio (`geocoding_source = municipality_centroid`) y se fuerza `city_only` como visibilidad efectiva (no tiene sentido "aproximada" sin punto real). La UI lo explica (callout "Sin punto en el mapa, la ubicación se publica como «Solo municipio»" mientras la visibilidad elegida sea `exact` o `approximate` sin pin). Implementado en Phase 2 (`SaveBusinessLocation` + `PublicPointDeriver`, `Location::effectiveVisibility()`) y completado en Phase 5 con el picker `x-map.picker` (parcial `partials/location-fields`, compartido por el formulario de empresa y el paso 5 del wizard a través de la clase base `App\Livewire\LocationPickerComponent`): el mapa escribe `latitude`/`longitude` en dos inputs ocultos con `wire:model.live`, los hooks `updatedLocationLatitude/Longitude` marcan `manual_pin`, y "Quitar el punto" (`clearLocationPoint`) vuelve al centroide. El radio de `city_only` sale de `config('avytra.location.city_only_radius_m')` por tramos de población (< 5.000 → 1,5 km; < 20.000 → 2 km; < 100.000 → 3 km; < 500.000 → 4 km; resto 5 km; sin dato 3 km).
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

- Un único módulo `resources/js/map/` con tres montadores (`mountListingMap`, `mountExploreMap`, `mountLocationPicker`) y tres componentes Blade (`x-map.listing`, `x-map.explore`, `x-map.picker`) que emiten el HTML y el fallback.
- Colores y estilo desde config/tokens, no literales en JS.
- El problema del worker de MapLibre con Vite se verifica en Phase 5 con la versión actual de MapLibre y Vite 8; si persiste, se resuelve con un paso de build (copia con checksum) en lugar de archivos vendorizados a mano.
- Ningún endpoint público devuelve todas las coordenadas: el mapa de explorar solo recibe los puntos de la página actual, y solo `public_*`.

Implementación (Phase 5, `maplibre-gl` 6.x aprobado el 2026-09-23):

- Entrada Vite separada `resources/js/map.js` (solo la incluye, vía `@vite`, cada componente de mapa, como hace `passkeys.js`): importa MapLibre y su CSS, y el worker con `import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'` + `setWorkerUrl(workerUrl)`. MapLibre v6 resuelve el worker relativo a `import.meta.url`, que Vite reescribe; con `?worker&url` es Vite quien lo empaqueta (`public/build/assets/maplibre-gl-worker-*.js`) y lo sirve en desarrollo (origen distinto, MapLibre lo carga mediante un blob `import`). **Sin archivos vendorizados.** Verificado en navegador en build y en `npm run dev`.
- Cada componente es un contenedor con `data-map="listing|explore|picker"` y atributos `data-*` renderizados por Blade, un canvas `wire:ignore` (oculto hasta que el mapa se crea) y un bloque `[data-map-fallback]` visible por defecto (sirve también sin JavaScript). `resources/js/map/support.js` comprueba WebGL2 y muestra el fallback si falla; `tokens.js` lee Ink y Transfer Blue de las variables CSS del tema; `geometry.js` genera el polígono del círculo y sus límites; `layers.js` pinta el área (relleno 15 %, borde 60 %).
- Montaje desacoplado de Livewire: el módulo monta todos los `[data-map]` al cargar y en `livewire:navigated`, desmonta en `livewire:navigating`, y cada contenedor lleva un `x-data` mínimo cuyo `init`/`destroy` emite `avytra:map-mount` / `avytra:map-unmount`, de modo que los mapas que aparecen o desaparecen en un re-render (toggle de explorar, cambio de paso en el wizard) se crean y destruyen (`map.remove()`) sin fugas de contextos WebGL. Los cambios de datos tras un re-render (puntos de explorar, pin o municipio del picker) llegan como cambios de atributos `data-*` que cada montador observa con `MutationObserver`.
- Configuración en `config('avytra.map')`: `style_url`, `default_centre` (España), `zoom.{country, municipality, exact}`.

### Render según visibilidad

- `exact`: marcador (símbolo AVYTRA en Ink) + popup con dirección.
- `approximate` / `city_only`: capa `circle` con relleno Transfer Blue al 15 % y borde al 60 %, sin marcador; texto bajo el mapa "Ubicación aproximada. La dirección exacta se facilita al contactar."
- `hidden`: sin mapa; texto "Provincia de X".

Fallback sin WebGL: bloque con el texto de ubicación y enlace "Ver zona en OpenStreetMap" (solo para `exact`; para aproximadas, enlace al municipio).

Implementación (Phase 5): `x-map.listing` recibe el `PublicListingPresenter` y usa solo `publicPoint()`: `data-lat/lng` públicos, `data-radius` vacío para `exact` (marcador Ink con popup de la dirección pública) y con metros para el resto (polígono circular ajustado con `fitBounds`); para `hidden` no emite nada. El texto explicativo lo aporta el bloque de ubicación de la ficha (`locationExplanation()`), no el mapa. El enlace del fallback apunta a OpenStreetMap con las coordenadas públicas (`?mlat/mlon` solo para `exact`). El mapa de explorar (`x-map.explore`, toggle `mapa` en la URL) recibe `PublicListingPresenter::mapPoint()` de las tarjetas de la página actual: marcador para exactas, círculo para aproximadas, popup con título y enlace construido con nodos DOM (nunca HTML de vendedor).

## Geocodificación (opcional, abstraída)

```php
namespace App\Services\Geocoding;

interface Geocoder
{
    /** False con el driver null: los formularios ocultan "Buscar la dirección en el mapa". */
    public function isAvailable(): bool;

    /** @throws GeocodingUnavailable si el proveedor no responde o alcanza su límite */
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

Implementación (Phase 5): `App\Services\Geocoding\{Geocoder, GeocodingResult, NullGeocoder, NominatimGeocoder}`, enlazados en `AppServiceProvider` según `config('avytra.geocoding.driver')` (`null` por defecto). `NominatimGeocoder` envía `User-Agent` y `email` de `config('avytra.geocoding.nominatim')`, limita a `requests_per_second` con `RateLimiter` (clave `geocoding:nominatim`; si se supera lanza `App\Exceptions\GeocodingUnavailable` en vez de esperar) y cachea cada consulta normalizada, también las sin resultado, durante `cache_days`. El botón "Buscar la dirección en el mapa" (`LocationPickerComponent::searchAddress`) solo se renderiza si `isAvailable()`, exige municipio y dirección, aplica el limitador `geocode` por usuario (`rate_limit_per_hour`), rellena `latitude`/`longitude` con `geocoding_source = geocoder` y el proveedor, completa el código postal si estaba vacío y avisa con un toast; `SaveBusinessLocation` fija `geocoded_at` y borra proveedor y fecha si el punto pasa a ser manual. Un pin arrastrado después de geocodificar vuelve a `manual_pin`.

## Filtros geográficos

MVP: solo provincia (FK). Radio/distancia: fuera del MVP; si llega, prefiltro por bounding box sobre el índice `(public_latitude, public_longitude)` y Haversine en PHP o SQL sobre el subconjunto, nunca sobre coordenadas privadas.

## Tests previstos

- Derivación de `public_*` para cada visibilidad (unit).
- Determinismo del jitter y contención dentro del radio.
- La proyección pública nunca contiene `latitude`/`longitude`/`address_line` salvo `exact`.
- Componente de mapa emite fallback y `data-*` correctos según visibilidad (feature, HTML).
- Publicar físico sin provincia/municipio falla; online con ubicación falla.
