import { describe, expect, test } from 'vitest';
import { projectPoints } from './routePreview';

describe('projectPoints', () => {
    test('empty stays empty', () => expect(projectPoints([], 160, 90, 10)).toEqual([]));

    test('a single point is centred', () => {
        expect(projectPoints([[50, 14]], 160, 90, 10)).toEqual([{ x: 80, y: 45 }]);
    });

    test('north is up and everything stays inside the padding', () => {
        const pts = projectPoints([[50.001, 14], [50, 14.001], [49.999, 14]], 160, 90, 10);
        expect(pts[0].y).toBeLessThan(pts[2].y);
        pts.forEach(({ x, y }) => {
            expect(x).toBeGreaterThanOrEqual(10); expect(x).toBeLessThanOrEqual(150);
            expect(y).toBeGreaterThanOrEqual(10); expect(y).toBeLessThanOrEqual(80);
        });
    });

    test('keeps the aspect ratio (a tall route is not stretched wide)', () => {
        const [a, b] = projectPoints([[50, 14], [50.01, 14]], 160, 90, 10);
        expect(a.x).toBeCloseTo(b.x, 5);
    });
});
