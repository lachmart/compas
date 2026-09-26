import { magvar } from 'magvar';

// Phone compasses (iOS webkitCompassHeading, Android's absolute
// orientation) point to MAGNETIC north, while bearings to a station are
// computed from coordinates against TRUE north. In Czechia magnetic north
// lies ~5–6° east, which turned every arrow that much to the right. The
// World Magnetic Model gives the difference for the player's position.

const cache = new Map();

// Degrees to add to a magnetic heading to get a true one (east positive).
// Cached per ~10 km cell; 0 when the model can't answer (e.g. past 2030).
export function declinationAt(lat, lng) {
    const key = `${lat.toFixed(1)},${lng.toFixed(1)}`;

    if (!cache.has(key)) {
        let value = 0;

        try {
            value = magvar(lat, lng);
        } catch {
            value = 0;
        }

        cache.set(key, Number.isFinite(value) ? value : 0);
    }

    return cache.get(key);
}

export function trueHeading(magneticHeading, declination) {
    return (((magneticHeading + declination) % 360) + 360) % 360;
}
