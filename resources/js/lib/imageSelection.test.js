import { describe, expect, test } from 'vitest';
import { correctIndexAfterRemoval } from './imageSelection';

describe('correctIndexAfterRemoval', () => {
    test('clears the choice when the correct image is removed', () => {
        expect(correctIndexAfterRemoval(2, 2)).toBeNull();
    });

    test('shifts the choice left when an earlier image is removed', () => {
        expect(correctIndexAfterRemoval(3, 1)).toBe(2);
    });

    test('keeps the choice when a later image is removed', () => {
        expect(correctIndexAfterRemoval(1, 4)).toBe(1);
    });

    test('keeps no choice as no choice', () => {
        expect(correctIndexAfterRemoval(null, 0)).toBeNull();
    });
});
