const EARTH_RADIUS_M = 6371000;

function toRadians(degrees) {
    return (degrees * Math.PI) / 180;
}

function toDegrees(radians) {
    return (radians * 180) / Math.PI;
}

export function distanceMeters(lat1, lng1, lat2, lng2) {
    const dLat = toRadians(lat2 - lat1);
    const dLng = toRadians(lng2 - lng1);
    const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRadians(lat1)) * Math.cos(toRadians(lat2)) * Math.sin(dLng / 2) ** 2;
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return EARTH_RADIUS_M * c;
}

export function bearingDegrees(lat1, lng1, lat2, lng2) {
    const y = Math.sin(toRadians(lng2 - lng1)) * Math.cos(toRadians(lat2));
    const x =
        Math.cos(toRadians(lat1)) * Math.sin(toRadians(lat2)) -
        Math.sin(toRadians(lat1)) * Math.cos(toRadians(lat2)) * Math.cos(toRadians(lng2 - lng1));

    return (toDegrees(Math.atan2(y, x)) + 360) % 360;
}

export function arrowRotationDegrees(bearing, heading) {
    return (bearing - heading + 360) % 360;
}

export function proximityColor(distance) {
    if (distance < 20) return 'red';
    if (distance < 50) return 'orange';
    if (distance < 150) return 'yellow';

    return 'blue';
}
