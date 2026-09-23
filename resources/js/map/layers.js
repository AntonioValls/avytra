/*
 * "Approximate area" rendering (docs/10): Transfer Blue fill at 15 % and outline at 60 %.
 */
export const AREA_FILL_OPACITY = 0.15;
export const AREA_LINE_OPACITY = 0.6;

export function emptyCollection() {
    return { type: 'FeatureCollection', features: [] };
}

export function addAreaLayers(map, sourceId, data, tokens) {
    map.addSource(sourceId, { type: 'geojson', data });

    map.addLayer({
        id: `${sourceId}-fill`,
        type: 'fill',
        source: sourceId,
        paint: { 'fill-color': tokens.transfer, 'fill-opacity': AREA_FILL_OPACITY },
    });

    map.addLayer({
        id: `${sourceId}-line`,
        type: 'line',
        source: sourceId,
        paint: { 'line-color': tokens.transfer, 'line-opacity': AREA_LINE_OPACITY, 'line-width': 2 },
    });
}

export function setAreaData(map, sourceId, data) {
    map.getSource(sourceId)?.setData(data);
}
