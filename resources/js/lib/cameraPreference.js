import { readStorage, writeStorage } from '@/lib/safeStorage';

// Whether the player chose to play with the camera (AR aim) when the join
// screen explained the permissions. 'declined' means the game must never
// ask for the camera by surprise later; null = not decided yet.

const STORAGE_KEY = 'kompas.camera';

export function cameraPreference(storage = globalThis.localStorage) {
    const value = readStorage(storage, STORAGE_KEY);

    return value === 'granted' || value === 'declined' ? value : null;
}

export function setCameraPreference(value, storage = globalThis.localStorage) {
    writeStorage(storage, STORAGE_KEY, value);
}

// Asks the browser for the camera (call it from a tap) and remembers the
// answer. Resolves to 'granted', 'blocked' (the browser refused — also
// without asking when the player refused earlier; only its site settings
// can undo that) or 'unsupported'.
export async function requestCamera(mediaDevices = globalThis.navigator?.mediaDevices, storage = globalThis.localStorage) {
    if (!mediaDevices?.getUserMedia) {
        setCameraPreference('declined', storage);

        return 'unsupported';
    }

    try {
        const stream = await mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
        stream.getTracks().forEach((track) => track.stop());
        setCameraPreference('granted', storage);

        return 'granted';
    } catch {
        setCameraPreference('declined', storage);

        return 'blocked';
    }
}
