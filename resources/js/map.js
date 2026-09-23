import * as maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
import { mountExploreMap } from './map/explore';
import { mountListingMap } from './map/listing';
import { mountLocationPicker } from './map/picker';

/*
 * AVYTRA map module (docs/10-location-and-maps.md). Loaded only on pages that render a map
 * component (x-map.listing, x-map.explore, x-map.picker). Each component is a wrapper with
 * data-map="<kind>" and data-* attributes rendered by Blade; the map itself lives in a
 * wire:ignore canvas so Livewire never touches it. Livewire re-renders update the wrapper's
 * data-* attributes, which every mounter watches with a MutationObserver.
 *
 * MapLibre v6 resolves its web worker relative to import.meta.url, which does not survive
 * Vite's bundling. Importing the worker with ?worker&url lets Vite bundle and serve it
 * (in dev and build) so no file has to be copied by hand.
 */
maplibregl.setWorkerUrl(workerUrl);

const MOUNTERS = {
    listing: mountListingMap,
    explore: mountExploreMap,
    picker: mountLocationPicker,
};

const instances = new WeakMap();

function mount(element) {
    if (!(element instanceof HTMLElement) || element.dataset.mapInitialized) {
        return;
    }

    const mounter = MOUNTERS[element.dataset.map];

    if (!mounter) {
        return;
    }

    element.dataset.mapInitialized = 'true';

    const instance = mounter(element, maplibregl);

    if (instance) {
        instances.set(element, instance);
    }
}

function unmount(element) {
    if (!(element instanceof HTMLElement)) {
        return;
    }

    instances.get(element)?.destroy();
    instances.delete(element);
    delete element.dataset.mapInitialized;
}

function mountAll() {
    document.querySelectorAll('[data-map]').forEach(mount);
}

function unmountAll() {
    document.querySelectorAll('[data-map]').forEach(unmount);
}

// Initial load (module scripts run before DOMContentLoaded) and wire:navigate arrivals.
mountAll();
document.addEventListener('livewire:navigated', mountAll);
document.addEventListener('livewire:navigating', unmountAll);

// Wrappers added or removed by a Livewire render announce themselves from Alpine's init/destroy.
window.addEventListener('avytra:map-mount', (event) => mount(event.target));
window.addEventListener('avytra:map-unmount', (event) => unmount(event.detail));
