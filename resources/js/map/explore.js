import { boundsOf, circleFeature } from './geometry';
import { addAreaLayers, emptyCollection, setAreaData } from './layers';
import { createMap, numberOrNull } from './support';
import { brandTokens } from './tokens';

const FIT_PADDING_PX = 48;

function readPoints(wrapper) {
    try {
        const points = JSON.parse(wrapper.dataset.points || '[]');

        return Array.isArray(points) ? points.filter((point) => numberOrNull(point.lat) !== null && numberOrNull(point.lng) !== null) : [];
    } catch {
        return [];
    }
}

/**
 * Popup content built from DOM nodes: titles come from sellers and must never be injected as HTML.
 */
function popupContent(point) {
    const box = document.createElement('div');
    box.className = 'flex flex-col gap-1 text-sm';

    const title = document.createElement(point.url ? 'a' : 'span');
    title.className = 'font-semibold text-ink';
    title.textContent = point.title ?? '';

    if (point.url) {
        title.href = point.url;
        title.className += ' hover:text-transfer hover:underline';
    }

    const text = document.createElement('span');
    text.className = 'text-slate';
    text.textContent = point.text ?? '';

    box.append(title, text);

    return box;
}

/**
 * Map of the explore page: the public points of the current page only. Pins for exact
 * locations, circles for approximate ones; data-points changes with each Livewire render.
 */
export function mountExploreMap(wrapper, maplibregl) {
    const tokens = brandTokens();
    const centre = [numberOrNull(wrapper.dataset.centreLng) ?? 0, numberOrNull(wrapper.dataset.centreLat) ?? 0];
    const zoomCountry = numberOrNull(wrapper.dataset.zoomCountry) ?? 5;
    const zoomExact = numberOrNull(wrapper.dataset.zoomExact) ?? 16;

    const map = createMap(maplibregl, wrapper, {
        style: wrapper.dataset.styleUrl,
        center: centre,
        zoom: zoomCountry,
        cooperativeGestures: true,
    });

    if (!map) {
        return null;
    }

    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    let markers = [];
    let popup = null;
    let loaded = false;

    const render = () => {
        const points = readPoints(wrapper);

        markers.forEach((marker) => marker.remove());
        markers = [];
        popup?.remove();

        const areas = points.filter((point) => numberOrNull(point.radius));
        const pins = points.filter((point) => !numberOrNull(point.radius));

        setAreaData(map, 'areas', {
            type: 'FeatureCollection',
            features: areas.map((point) => circleFeature(Number(point.lng), Number(point.lat), Number(point.radius), { title: point.title, url: point.url, text: point.text })),
        });

        pins.forEach((point) => {
            const marker = new maplibregl.Marker({ color: tokens.ink })
                .setLngLat([Number(point.lng), Number(point.lat)])
                .setPopup(new maplibregl.Popup({ offset: 24, closeButton: false }).setDOMContent(popupContent(point)))
                .addTo(map);

            markers.push(marker);
        });

        const bounds = boundsOf(points.map((point) => ({ lat: Number(point.lat), lng: Number(point.lng), radius: numberOrNull(point.radius) })));

        if (bounds) {
            map.fitBounds(bounds, { padding: FIT_PADDING_PX, maxZoom: zoomExact, animate: false });
        } else {
            map.jumpTo({ center: centre, zoom: zoomCountry });
        }
    };

    map.on('load', () => {
        addAreaLayers(map, 'areas', emptyCollection(), tokens);

        map.on('click', 'areas-fill', (event) => {
            const feature = event.features?.[0];

            if (!feature) {
                return;
            }

            popup?.remove();
            popup = new maplibregl.Popup({ closeButton: false }).setLngLat(event.lngLat).setDOMContent(popupContent(feature.properties)).addTo(map);
        });

        map.on('mouseenter', 'areas-fill', () => {
            map.getCanvas().style.cursor = 'pointer';
        });

        map.on('mouseleave', 'areas-fill', () => {
            map.getCanvas().style.cursor = '';
        });

        loaded = true;
        render();
    });

    const observer = new MutationObserver(() => {
        if (loaded) {
            render();
        }
    });

    observer.observe(wrapper, { attributes: true, attributeFilter: ['data-points'] });

    return {
        destroy: () => {
            observer.disconnect();
            map.remove();
        },
    };
}
