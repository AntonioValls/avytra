import { circleBounds, circleFeature } from './geometry';
import { addAreaLayers } from './layers';
import { createMap, numberOrNull } from './support';
import { brandTokens } from './tokens';

const AREA_PADDING_PX = 24;

/**
 * Map of a listing page: a pin when the location is exact (no radius), a circle otherwise.
 * Reads only the derived public point that x-map.listing renders.
 */
export function mountListingMap(wrapper, maplibregl) {
    const lat = numberOrNull(wrapper.dataset.lat);
    const lng = numberOrNull(wrapper.dataset.lng);
    const radius = numberOrNull(wrapper.dataset.radius);

    if (lat === null || lng === null) {
        return null;
    }

    const tokens = brandTokens();
    const map = createMap(maplibregl, wrapper, {
        style: wrapper.dataset.styleUrl,
        center: [lng, lat],
        zoom: numberOrNull(wrapper.dataset.zoom) ?? 14,
        cooperativeGestures: true,
    });

    if (!map) {
        return null;
    }

    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    if (radius) {
        map.on('load', () => addAreaLayers(map, 'area', circleFeature(lng, lat, radius), tokens));
        map.fitBounds(circleBounds(lng, lat, radius), { padding: AREA_PADDING_PX, animate: false });
    } else {
        const marker = new maplibregl.Marker({ color: tokens.ink }).setLngLat([lng, lat]).addTo(map);
        const label = wrapper.dataset.label ?? '';

        if (label !== '') {
            marker.setPopup(new maplibregl.Popup({ offset: 24, closeButton: false }).setText(label));
        }
    }

    return { destroy: () => map.remove() };
}
