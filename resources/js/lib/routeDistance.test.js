import { describe, expect, test } from 'vitest';
import { formatDistance, routeLegs } from './routeDistance';

describe('routeLegs', () => {
    test('measures each leg and the total', () => {
        // 0.001° of latitude ≈ 111 m.
        const { legs, total } = routeLegs([
            { lat: 50.0, lng: 14.0 },
            { lat: 50.001, lng: 14.0 },
            { lat: 50.003, lng: 14.0 },
        ]);

        expect(legs.map(Math.round)).toEqual([111, 222]);
        expect(Math.round(total)).toBe(334);
    });

    test('fewer than two locations have no legs', () => {
        expect(routeLegs([])).toEqual({ legs: [], total: 0 });
        expect(routeLegs([{ lat: 50, lng: 14 }])).toEqual({ legs: [], total: 0 });
    });
});

describe('formatDistance', () => {
    test('metres below a kilometre, kilometres with a comma above', () => {
        expect(formatDistance(84.6)).toBe('85 m');
        expect(formatDistance(999.4)).toBe('999 m');
        expect(formatDistance(1000)).toBe('1,0 km');
        expect(formatDistance(1234)).toBe('1,2 km');
    });
});
