import { describe, expect, test } from 'vitest';
import { declinationAt, trueHeading } from './declination';

describe('declinationAt', () => {
    test('magnetic north is ~5–6° east across Czechia (WMM2025)', () => {
        expect(declinationAt(49.6, 17.27)).toBeGreaterThan(5);
        expect(declinationAt(49.6, 17.27)).toBeLessThan(6.5);
        expect(declinationAt(50.08, 12.37)).toBeGreaterThan(4);
        expect(declinationAt(50.08, 12.37)).toBeLessThan(declinationAt(49.6, 17.27));
    });

    test('nearby points share one cached value', () => {
        expect(declinationAt(49.601, 17.271)).toBe(declinationAt(49.604, 17.274));
    });
});

describe('trueHeading', () => {
    test('adds the declination and wraps', () => {
        expect(trueHeading(90, 5.5)).toBeCloseTo(95.5);
        expect(trueHeading(357, 5)).toBeCloseTo(2);
        expect(trueHeading(2, -5)).toBeCloseTo(357);
    });
});
