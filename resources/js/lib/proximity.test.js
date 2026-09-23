import { describe, expect, test } from 'vitest';
import { HEAT_LEVELS, heatGradient, heatLevel, unwrapAngle } from './proximity';

describe('heatLevel', () => {
    test('no distance yet is the coldest level', () => {
        expect(heatLevel(null)).toBe(0);
        expect(heatLevel(undefined)).toBe(0);
    });

    test.each([
        [5000, 0],
        [300, 0],
        [299.9, 1],
        [150, 1],
        [149.9, 2],
        [75, 2],
        [74.9, 3],
        [35, 3],
        [34.9, 4],
        [15, 4],
        [14.9, 5],
        [0, 5],
    ])('%s m is level %s', (distance, level) => {
        expect(heatLevel(distance)).toBe(level);
    });
});

describe('heatGradient', () => {
    test('every level is a top-to-bottom gradient', () => {
        for (let level = 0; level <= HEAT_LEVELS; level++) {
            expect(heatGradient(level)).toMatch(/^bg-gradient-to-b from-\S+ to-\S+$/);
        }
    });

    test('runs from cold blue to hot red', () => {
        expect(heatGradient(0)).toContain('from-blue-');
        expect(heatGradient(2)).toContain('from-teal-');
        expect(heatGradient(3)).toContain('from-yellow-');
        expect(heatGradient(4)).toContain('from-orange-');
        expect(heatGradient(5)).toContain('from-red-');
    });

    test('each level has its own colours', () => {
        const all = Array.from({ length: HEAT_LEVELS + 1 }, (_, level) => heatGradient(level));
        expect(new Set(all).size).toBe(HEAT_LEVELS + 1);
    });

    test('out-of-range levels are clamped', () => {
        expect(heatGradient(-1)).toBe(heatGradient(0));
        expect(heatGradient(9)).toBe(heatGradient(5));
    });
});

describe('unwrapAngle', () => {
    test('without a previous angle returns the next one', () => {
        expect(unwrapAngle(null, 42)).toBe(42);
    });

    test('small moves stay as they are', () => {
        expect(unwrapAngle(10, 20)).toBe(20);
        expect(unwrapAngle(20, 10)).toBe(10);
    });

    test('crossing 359 -> 0 keeps turning forward', () => {
        expect(unwrapAngle(359, 1)).toBe(361);
        expect(unwrapAngle(350, 10)).toBe(370);
    });

    test('crossing 0 -> 359 keeps turning backward', () => {
        expect(unwrapAngle(1, 359)).toBe(-1);
        expect(unwrapAngle(10, 350)).toBe(-10);
    });

    test('works from an already unwrapped angle', () => {
        expect(unwrapAngle(725, 10)).toBe(730);
        expect(unwrapAngle(-355, 350)).toBe(-370);
    });

    test('accepts next angles outside 0..360', () => {
        expect(unwrapAngle(0, -20)).toBe(-20);
        expect(unwrapAngle(0, 380)).toBe(20);
    });
});
