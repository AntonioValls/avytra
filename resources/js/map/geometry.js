const EARTH_RADIUS_M = 6371000;

/**
 * GeoJSON polygon approximating a circle of radiusM metres around [lng, lat].
 */
export function circlePolygon(lng, lat, radiusM, steps = 64) {
    const latRad = (lat * Math.PI) / 180;
    const ring = [];

    for (let i = 0; i <= steps; i++) {
        const bearing = (2 * Math.PI * i) / steps;
        const deltaLat = (radiusM * Math.cos(bearing)) / EARTH_RADIUS_M;
        const deltaLng = (radiusM * Math.sin(bearing)) / (EARTH_RADIUS_M * Math.cos(latRad));

        ring.push([lng + (deltaLng * 180) / Math.PI, lat + (deltaLat * 180) / Math.PI]);
    }

    return { type: 'Polygon', coordinates: [ring] };
}

export function circleFeature(lng, lat, radiusM, properties = {}) {
    return { type: 'Feature', geometry: circlePolygon(lng, lat, radiusM), properties };
}

/**
 * [[west, south], [east, north]] enclosing the circle, for fitBounds().
 */
export function circleBounds(lng, lat, radiusM) {
    const deltaLat = ((radiusM / EARTH_RADIUS_M) * 180) / Math.PI;
    const deltaLng = deltaLat / Math.cos((lat * Math.PI) / 180);

    return [
        [lng - deltaLng, lat - deltaLat],
        [lng + deltaLng, lat + deltaLat],
    ];
}

/**
 * Smallest [[west, south], [east, north]] containing every point (with its radius, if any).
 */
export function boundsOf(points) {
    let west = Infinity;
    let south = Infinity;
    let east = -Infinity;
    let north = -Infinity;

    for (const point of points) {
        const [[w, s], [e, n]] = point.radius ? circleBounds(point.lng, point.lat, point.radius) : [[point.lng, point.lat], [point.lng, point.lat]];

        west = Math.min(west, w);
        south = Math.min(south, s);
        east = Math.max(east, e);
        north = Math.max(north, n);
    }

    return Number.isFinite(west) ? [[west, south], [east, north]] : null;
}
