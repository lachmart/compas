import { describe, expect, test } from 'vitest';
import { cameraPreference, requestCamera, setCameraPreference } from './cameraPreference';
import { brokenStorage, memoryStorage } from './testing/storage';

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

        const broken = brokenStorage();
        expect(cameraPreference(broken)).toBeNull();
        expect(() => setCameraPreference('granted', broken)).not.toThrow();
    });
});

describe('requestCamera', () => {
    test('a granted camera is remembered', async () => {
        const storage = memoryStorage();

        expect(await requestCamera(async () => ({}), storage)).toBe('granted');
        expect(cameraPreference(storage)).toBe('granted');
    });

    test('a refused or missing camera is remembered as declined', async () => {
        const storage = memoryStorage();

        expect(await requestCamera(async () => { throw new Error('NotAllowedError'); }, storage)).toBe('blocked');
        expect(cameraPreference(storage)).toBe('declined');
    });
});
