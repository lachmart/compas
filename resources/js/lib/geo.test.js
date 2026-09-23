import { describe, expect, test } from 'vitest';
import { arrowRotationDegrees, bearingDegrees, distanceMeters, proximityColor } from './geo';

describe('distanceMeters', () => {
    test('returns ~0 for identical points', () => {
        expect(distanceMeters(50, 14, 50, 14)).toBeCloseTo(0, 5);
    });

    test('computes a known distance (Praha -> Brno, ~185 km)', () => {
        const d = distanceMeters(50.0755, 14.4378, 49.1951, 16.6068);
        expect(d).toBeGreaterThan(180000);
        expect(d).toBeLessThan(190000);
    });
});

describe('bearingDegrees', () => {
    test('due north is ~0 degrees', () => {
        expect(bearingDegrees(50, 14, 51, 14)).toBeCloseTo(0, 0);
    });

    test('due east is ~90 degrees', () => {
        expect(bearingDegrees(50, 14, 50, 15)).toBeCloseTo(90, 0);
    });

    test('due south is ~180 degrees', () => {
        expect(bearingDegrees(51, 14, 50, 14)).toBeCloseTo(180, 0);
    });
});

describe('arrowRotationDegrees', () => {
    test('facing the bearing yields zero rotation', () => {
        expect(arrowRotationDegrees(90, 90)).toBe(0);
    });

    test('wraps correctly around 360', () => {
        expect(arrowRotationDegrees(10, 350)).toBe(20);
    });
});

describe('proximityColor', () => {
    test.each([
        [10, 'red'],
        [30, 'orange'],
        [100, 'yellow'],
        [200, 'blue'],
    ])('distance %i meters -> %s', (distance, expected) => {
        expect(proximityColor(distance)).toBe(expected);
    });
});
