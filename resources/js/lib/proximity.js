// "Hot and cold" for the compass screen: how close the child is to the
// target, as a level from 0 (far, cold blue) to 5 (right there, hot red).

export const HEAT_LEVELS = 5;

// Lower bounds (metres) of levels 0..4; anything closer is level 5.
const THRESHOLDS = [300, 150, 75, 35, 15];

export function heatLevel(distanceM) {
    if (distanceM === null || distanceM === undefined) return 0;

    const index = THRESHOLDS.findIndex((threshold) => distanceM >= threshold);

    return index === -1 ? HEAT_LEVELS : index;
}

// Full class strings (never assembled) so Tailwind's scanner finds them.
const GRADIENTS = [
    'bg-gradient-to-b from-blue-500 to-indigo-800',
    'bg-gradient-to-b from-sky-400 to-blue-700',
    'bg-gradient-to-b from-teal-300 to-cyan-700',
    'bg-gradient-to-b from-yellow-300 to-amber-500',
    'bg-gradient-to-b from-orange-400 to-orange-700',
    'bg-gradient-to-b from-red-500 to-rose-800',
];

export function heatGradient(level) {
    return GRADIENTS[Math.min(HEAT_LEVELS, Math.max(0, level))];
}

// Returns the angle equivalent to `next` (mod 360) that is closest to
// `prev`, so a CSS rotation transition turns the short way across
// 359 -> 0 instead of spinning all the way round.
export function unwrapAngle(prev, next) {
    if (prev === null || prev === undefined) return next;

    const delta = ((((next - prev) % 360) + 540) % 360) - 180;

    return prev + delta;
}
