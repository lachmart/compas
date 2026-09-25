// Pure orientation maths for the player's compass. Angles in degrees.
// alpha is absolute: 0 = top edge of a flat phone points north, growing
// counter-clockwise (W3C Device Orientation convention).

// The camera view only takes over when the phone is held really upright
// (a light tilt while walking keeps the flat compass).
export const UPRIGHT_ENTER_DEG = 70;
export const UPRIGHT_EXIT_DEG = 50;
// Horizontal field of view the camera screen maps the world onto.
export const AR_FOV_DEG = 50;
// Aimed at the target (enter / leave, hysteresis against flicker).
export const AIM_DEG = 5;
export const AIM_EXIT_DEG = 8;
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

// Direction the back camera faces, for the upright AR view. iOS reports its
// own compass heading (webkitCompassHeading), which already follows the
// camera when the phone is upright; combining it with beta/gamma instead
// made the target jump, because iOS flips gamma as the phone passes
// vertical. Other browsers give Euler angles, turned into the camera
// direction by cameraHeading().
export function arCameraHeading({ alpha, beta, gamma, compassHeading }) {
    if (typeof compassHeading === 'number') return normalize360(compassHeading);

    return cameraHeading(alpha, beta, gamma);
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

// Signed shortest turn from `from` to `to`, in (-180, 180]; positive is
// clockwise.
export function angleDiff(from, to) {
    const diff = normalize360(to - from);

    return diff > 180 ? diff - 360 : diff;
}

// Where the target sits in the camera view. bearing: direction to the
// target; heading: direction the camera faces; wasAligned: the previous
// `aligned`, for hysteresis.
//   offset    -1 (left edge) … 1 (right edge), null when outside the view
//   direction 'left' | 'right' | 'behind' when outside the view, else null
//   aligned   aimed at the target
export function aimTarget(bearing, heading, wasAligned = false) {
    const diff = angleDiff(heading, bearing);
    const half = AR_FOV_DEG / 2;
    const inView = Math.abs(diff) <= half;

    let direction = null;
    if (!inView) direction = Math.abs(diff) > BEHIND_DEG ? 'behind' : diff > 0 ? 'right' : 'left';

    return {
        offset: inView ? diff / half : null,
        direction,
        aligned: Math.abs(diff) <= (wasAligned ? AIM_EXIT_DEG : AIM_DEG),
    };
}

// Low-pass filter for a compass angle: moves `previous` a `factor` of the
// shortest way towards `next`, so the on-screen target doesn't jitter.
export function smoothAngle(previous, next, factor) {
    if (previous === null || previous === undefined) return normalize360(next);

    return normalize360(previous + factor * angleDiff(previous, next));
}

// Normalises a deviceorientation(absolute) event into absolute angles, or
// null when the event carries no absolute heading (relative-only sources).
export function absoluteOrientation(event) {
    const { alpha, beta, gamma, absolute, webkitCompassHeading } = event;

    if (typeof beta !== 'number' || typeof gamma !== 'number') return null;

    if (typeof webkitCompassHeading === 'number') {
        // compassHeading is kept as iOS reports it: see arCameraHeading().
        return { alpha: normalize360(360 - webkitCompassHeading), beta, gamma, compassHeading: webkitCompassHeading };
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

// On-screen size (px) of the AR target: small far away, big close by, on a
// log scale so the last tens of metres grow the most.
export const AR_TARGET_FAR = { meters: 300, px: 56 };
export const AR_TARGET_NEAR = { meters: 10, px: 128 };

export function arTargetSize(distanceMeters) {
    if (distanceMeters === null || distanceMeters === undefined) return 80;

    const clamped = Math.min(AR_TARGET_FAR.meters, Math.max(AR_TARGET_NEAR.meters, distanceMeters));
    const t = Math.log(AR_TARGET_FAR.meters / clamped) / Math.log(AR_TARGET_FAR.meters / AR_TARGET_NEAR.meters);

    return Math.round(AR_TARGET_FAR.px + t * (AR_TARGET_NEAR.px - AR_TARGET_FAR.px));
}

// Exponential easing for per-frame animation: moves `current` towards
// `target` by the share of the gap a `timeConstantMs` decay covers in
// `dtMs`, so the motion looks the same at any frame rate.
export function easeTowards(current, target, dtMs, timeConstantMs) {
    if (current === null || current === undefined) return target;

    return current + (target - current) * (1 - Math.exp(-dtMs / timeConstantMs));
}
