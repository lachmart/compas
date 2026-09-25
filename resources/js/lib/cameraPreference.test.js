import { describe, expect, test } from 'vitest';
import { cameraPreference, setCameraPreference } from './cameraPreference';

const memoryStorage = () => {
    const data = new Map();

    return { getItem: (key) => data.get(key) ?? null, setItem: (key, value) => data.set(key, String(value)) };
};

describe('cameraPreference', () => {
    test('is undecided until the player chooses', () => {
        expect(cameraPreference(memoryStorage())).toBeNull();
    });

    test('remembers a grant or a refusal', () => {
        const storage = memoryStorage();

        setCameraPreference('declined', storage);
        expect(cameraPreference(storage)).toBe('declined');

        setCameraPreference('granted', storage);
        expect(cameraPreference(storage)).toBe('granted');
    });

    test('ignores unknown values and broken storage', () => {
        const storage = memoryStorage();
        storage.setItem('kompas.camera', 'maybe');
        expect(cameraPreference(storage)).toBeNull();

        const broken = { getItem: () => { throw new Error('blocked'); }, setItem: () => { throw new Error('blocked'); } };
        expect(cameraPreference(broken)).toBeNull();
        expect(() => setCameraPreference('granted', broken)).not.toThrow();
    });
});
