import { describe, expect, test } from 'vitest';
import { cameraPreference, requestCamera, setCameraPreference } from './cameraPreference';

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

describe('requestCamera', () => {
    const stream = () => {
        const track = { stopped: false, stop() { this.stopped = true; } };

        return { track, getTracks: () => [track] };
    };

    test('a granted camera is remembered and released right away', async () => {
        const storage = memoryStorage();
        const media = stream();

        expect(await requestCamera({ getUserMedia: async () => media }, storage)).toBe('granted');
        expect(cameraPreference(storage)).toBe('granted');
        expect(media.track.stopped).toBe(true);
    });

    test('a refused camera is remembered as declined', async () => {
        const storage = memoryStorage();

        expect(await requestCamera({ getUserMedia: async () => { throw new Error('NotAllowedError'); } }, storage)).toBe('blocked');
        expect(cameraPreference(storage)).toBe('declined');
    });

    test('no camera API means unsupported', async () => {
        const storage = memoryStorage();

        expect(await requestCamera(undefined, storage)).toBe('unsupported');
        expect(cameraPreference(storage)).toBe('declined');
    });
});
