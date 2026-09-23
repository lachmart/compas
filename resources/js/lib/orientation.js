// Pure orientation maths for the player's compass. Angles in degrees.
// alpha is absolute: 0 = top edge of a flat phone points north, growing
// counter-clockwise (W3C Device Orientation convention).

export const UPRIGHT_ENTER_DEG = 55;
export const UPRIGHT_EXIT_DEG = 35;
export const ON_COURSE_DEG = 20;
export const ON_COURSE_EXIT_DEG = 28;
export const BEHIND_DEG = 135;

const RAD = Math.PI / 180;

const normalize360 = (degrees) => ((degrees % 360) + 360) % 360;

// Compass direction (clockwise from north) the back camera looks along,
// i.e. the device's -z axis, from R = Rz(alpha) * Rx(beta) * Ry(gamma).
// Independent of portrait/landscape (a rotation about z doesn't move -z).
export function cameraHeading(alpha, beta, gamma) {
    const [sa, ca] = [Math.sin(alpha * RAD), Math.cos(alpha * RAD)];
    const sb = Math.sin(beta * RAD);
    const [sg, cg] = [Math.sin(gamma * RAD), Math.cos(gamma * RAD)];

    const east = -(ca * sg + sa * sb * cg);
    const north = -(sa * sg - ca * sb * cg);

    return normalize360(Math.atan2(east, north) / RAD);
}

// Angle between the device's z axis (out of the screen) and straight up:
// 0 flat screen-up, 90 upright or on its side.
export function tiltDegrees(beta, gamma) {
    const cosine = Math.cos(beta * RAD) * Math.cos(gamma * RAD);

    return Math.acos(Math.max(-1, Math.min(1, cosine))) / RAD;
}

export function nextPosture(previous, beta, gamma) {
    const tilt = tiltDegrees(beta, gamma);

    if (previous === 'flat' && tilt > UPRIGHT_ENTER_DEG) return 'upright';
    if (previous === 'upright' && tilt < UPRIGHT_EXIT_DEG) return 'flat';

    return previous;
}

// bearing: direction to the target; heading: direction the camera faces.
// previous: the last guidance value, used for hysteresis so that hovering
// right at the on-course edge doesn't flicker between 'on_course' and a
// turn direction on every reading.
export function turnGuidance(bearing, heading, previous = null) {
    let diff = normalize360(bearing - heading);
    if (diff > 180) diff -= 360;

    const tolerance = previous === 'on_course' ? ON_COURSE_EXIT_DEG : ON_COURSE_DEG;

    if (Math.abs(diff) <= tolerance) return 'on_course';
    if (Math.abs(diff) > BEHIND_DEG) return 'behind';

    return diff > 0 ? 'right' : 'left';
}

// Normalises a deviceorientation(absolute) event into absolute angles, or
// null when the event carries no absolute heading (relative-only sources).
export function absoluteOrientation(event) {
    const { alpha, beta, gamma, absolute, webkitCompassHeading } = event;

    if (typeof beta !== 'number' || typeof gamma !== 'number') return null;

    if (typeof webkitCompassHeading === 'number') {
        return { alpha: normalize360(360 - webkitCompassHeading), beta, gamma };
    }

    if (absolute === true && typeof alpha === 'number') {
        return { alpha, beta, gamma };
    }

    return null;
}

// Relative angles as a fallback for devices that never supply an absolute
// heading (e.g. a desktop with DevTools sensor emulation). North is then
// arbitrary — the same compromise the flat arrow already makes.
export function relativeOrientation(event) {
    const { alpha, beta, gamma } = event;

    if (typeof alpha !== 'number' || typeof beta !== 'number' || typeof gamma !== 'number') return null;

    return { alpha, beta, gamma };
}
