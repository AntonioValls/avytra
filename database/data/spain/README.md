# Catálogo geográfico de España

Archivos consumidos por `php artisan avytra:import-geography` (idempotente, upsert por código INE). No se editan a mano: se regeneran desde las fuentes.

| Archivo | Filas | Columnas |
|---|---|---|
| `regions.csv` | 19 (17 comunidades + Ceuta y Melilla) | `code` (INE, 2 dígitos), `name` |
| `provinces.csv` | 52 | `code` (INE, 2 dígitos), `region_code`, `name`, `latitude`, `longitude` |
| `municipalities.csv` | 8.131 | `code` (INE, 5 dígitos), `province_code`, `name`, `latitude`, `longitude`, `population` |

## Fuentes

- **Códigos, nombres y centroides de municipios, provincias y comunidades:** dataset `georef-spain-municipio` (edición 2022) publicado por Opendatasoft en su portal de datos públicos, derivado del *Nomenclátor Geográfico de Municipios* del Instituto Geográfico Nacional (IGN). Licencia de la fuente original: CC BY 4.0 (IGN). Descargado el 2026-09-22 con el export CSV de la API Explore v2.1 seleccionando `acom_code, acom_name, prov_code, prov_name, mun_code, mun_name, geo_point_2d`.
- **Población de municipios:** Wikidata (CC0), propiedad `P1082` (población) del ítem con código INE `P772`, tomando el valor con la fecha (`P585`) más reciente. Consulta SPARQL ejecutada el 2026-09-22. Solo se usa para dimensionar el radio del círculo público en la visibilidad "solo municipio".
- **Centroide de provincia:** media aritmética de los centroides de sus municipios (calculado en el procesado, no procede de ninguna fuente).

## Procesado aplicado

1. Se descartan las cinco plazas de soberanía sin provincia (código de provincia `54`) y las 81 entidades con código `53xxx` (comunidades de tierras, facerías, ledanías), que no son municipios.
2. Se deduplican los municipios que la fuente lista con dos nombres oficiales (bilingües) conservando uno: `03014` Alicante, `07032` Maó, `33018` Coaña, `33069` Soto del Barco, `33070` Tapia de Casariego.
3. Nombres de provincia normalizados a la forma habitual en castellano cuando la fuente usa la cooficial (Alicante, Castellón, Valencia, Álava). Se conservan las denominaciones oficiales únicas (Girona, Lleida, Ourense, A Coruña, Bizkaia, Gipuzkoa, Illes Balears).
4. Coordenadas redondeadas a 6 decimales. Los `slug` se generan en el comando de importación; si dos municipios de la misma provincia coinciden, se añade el código INE al segundo.

Resultado: 19 comunidades, 52 provincias, 8.131 municipios (todos con centroide y población).
