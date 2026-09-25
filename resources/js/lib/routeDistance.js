import { distanceMeters } from './geo';

// Straight-line ("as the crow flies") distances along the game's route:
// legs[i] is from location i to i + 1; total is their sum.
export function routeLegs(locations) {
    const legs = [];

    for (let i = 1; i < locations.length; i++) {
        const from = locations[i - 1];
        const to = locations[i];
        legs.push(distanceMeters(from.lat, from.lng, to.lat, to.lng));
    }

    return { legs, total: legs.reduce((sum, leg) => sum + leg, 0) };
}

// "85 m" below a kilometre, then "1,2 km" (Czech decimal comma).
export function formatDistance(meters) {
    if (meters < 1000) return `${Math.round(meters)} m`;

    return `${(meters / 1000).toFixed(1).replace('.', ',')} km`;
}
