/*
 * WebGL check and fallback. MapLibre v6 needs WebGL2; without it new Map() throws and the
 * container stays empty. Every wrapper carries a [data-map-fallback] block rendered by Blade
 * (visible by default, so it also serves visitors without JavaScript) and a hidden
 * [data-map-canvas]; the canvas is revealed only when the map can actually be created.
 */
export function canRenderMap() {
    try {
        const canvas = document.createElement('canvas');

        return Boolean(window.WebGL2RenderingContext && canvas.getContext('webgl2'));
    } catch {
        return false;
    }
}

function showFallback(wrapper) {
    const canvas = wrapper.querySelector('[data-map-canvas]');
    const fallback = wrapper.querySelector('[data-map-fallback]');

    if (canvas) {
        canvas.hidden = true;
    }

    if (fallback) {
        fallback.hidden = false;
    }
}

/**
 * Creates the map inside the wrapper's canvas or shows the fallback and returns null.
 */
export function createMap(maplibregl, wrapper, options) {
    const canvas = wrapper.querySelector('[data-map-canvas]');
    const fallback = wrapper.querySelector('[data-map-fallback]');

    if (!canvas || !canRenderMap()) {
        showFallback(wrapper);

        return null;
    }

    // The container must be visible before MapLibre measures it.
    canvas.hidden = false;

    if (fallback) {
        fallback.hidden = true;
    }

    try {
        const map = new maplibregl.Map({ container: canvas, ...options });

        map.once('webglcontextlost', () => showFallback(wrapper));

        return map;
    } catch (error) {
        console.warn('The map could not be initialised:', error);
        showFallback(wrapper);

        return null;
    }
}

export function numberOrNull(value) {
    if (value === undefined || value === null || value === '') {
        return null;
    }

    const number = Number(value);

    return Number.isFinite(number) ? number : null;
}

export function sameCoordinate(a, b) {
    if (a === null || b === null) {
        return a === b;
    }

    return Math.abs(a - b) < 1e-7;
}
