import { createMap, numberOrNull, sameCoordinate } from './support';
import { brandTokens } from './tokens';

const COORDINATE_DECIMALS = 7;

function round(value) {
    return Number(value.toFixed(COORDINATE_DECIMALS));
}

function readState(wrapper) {
    return {
        lat: numberOrNull(wrapper.dataset.lat),
        lng: numberOrNull(wrapper.dataset.lng),
        centreLat: numberOrNull(wrapper.dataset.centreLat),
        centreLng: numberOrNull(wrapper.dataset.centreLng),
    };
}

/**
 * Location picker for the business form and the wizard. The map centres on the chosen
 * municipality; a click or a dragged marker writes the private coordinates into the hidden
 * inputs bound with wire:model (an "input" event is what Livewire listens for). Livewire
 * re-renders update data-lat/data-lng/data-centre-*, which the observer reflects back.
 */
export function mountLocationPicker(wrapper, maplibregl) {
    const tokens = brandTokens();
    const inputs = {
        lat: wrapper.querySelector('[data-map-input="lat"]'),
        lng: wrapper.querySelector('[data-map-input="lng"]'),
    };
    const zoomCountry = numberOrNull(wrapper.dataset.zoomCountry) ?? 5;
    const zoomMunicipality = numberOrNull(wrapper.dataset.zoomMunicipality) ?? 13;
    const zoomExact = numberOrNull(wrapper.dataset.zoomExact) ?? 16;
    const fallbackCentre = [numberOrNull(wrapper.dataset.defaultLng) ?? 0, numberOrNull(wrapper.dataset.defaultLat) ?? 0];

    let state = readState(wrapper);

    const startView = () => {
        if (state.lat !== null && state.lng !== null) {
            return { center: [state.lng, state.lat], zoom: zoomExact };
        }

        if (state.centreLat !== null && state.centreLng !== null) {
            return { center: [state.centreLng, state.centreLat], zoom: zoomMunicipality };
        }

        return { center: fallbackCentre, zoom: zoomCountry };
    };

    const map = createMap(maplibregl, wrapper, { style: wrapper.dataset.styleUrl, ...startView() });

    if (!map) {
        return null;
    }

    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    let marker = null;

    const writeInputs = (lat, lng) => {
        for (const [key, value] of [['lat', lat], ['lng', lng]]) {
            const input = inputs[key];

            if (!input) {
                continue;
            }

            input.value = value === null ? '' : String(value);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
    };

    const placeMarker = (lng, lat) => {
        if (!marker) {
            marker = new maplibregl.Marker({ color: tokens.ink, draggable: true }).setLngLat([lng, lat]).addTo(map);

            marker.on('dragend', () => {
                const position = marker.getLngLat();

                state = { ...state, lat: round(position.lat), lng: round(position.lng) };
                writeInputs(state.lat, state.lng);
            });

            return;
        }

        marker.setLngLat([lng, lat]);
    };

    const removeMarker = () => {
        marker?.remove();
        marker = null;
    };

    if (state.lat !== null && state.lng !== null) {
        placeMarker(state.lng, state.lat);
    }

    map.on('click', (event) => {
        state = { ...state, lat: round(event.lngLat.lat), lng: round(event.lngLat.lng) };
        placeMarker(state.lng, state.lat);
        writeInputs(state.lat, state.lng);
    });

    // Server-side changes: the point cleared or geocoded, or another municipality chosen.
    const observer = new MutationObserver(() => {
        const next = readState(wrapper);
        const pointChanged = !sameCoordinate(next.lat, state.lat) || !sameCoordinate(next.lng, state.lng);
        const centreChanged = !sameCoordinate(next.centreLat, state.centreLat) || !sameCoordinate(next.centreLng, state.centreLng);

        state = next;

        if (pointChanged) {
            if (next.lat === null || next.lng === null) {
                removeMarker();
            } else {
                placeMarker(next.lng, next.lat);
                map.flyTo({ center: [next.lng, next.lat], zoom: Math.max(map.getZoom(), zoomExact) });
            }
        }

        if (centreChanged && next.lat === null && next.centreLat !== null && next.centreLng !== null) {
            map.flyTo({ center: [next.centreLng, next.centreLat], zoom: zoomMunicipality });
        }
    });

    observer.observe(wrapper, { attributes: true, attributeFilter: ['data-lat', 'data-lng', 'data-centre-lat', 'data-centre-lng'] });

    return {
        destroy: () => {
            observer.disconnect();
            map.remove();
        },
    };
}
