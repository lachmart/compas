import { acquireCamera, pauseCamera } from '@/lib/cameraStream';
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
// answer. The stream stays open but paused for the AR view (see
// cameraStream.js), so iOS doesn't ask again. Resolves to 'granted' or
// 'blocked' (no camera, or the browser refused — also without asking when
// the player refused earlier; only its site settings can undo that).
export async function requestCamera(acquire = acquireCamera, storage = globalThis.localStorage) {
    try {
        await acquire();
        pauseCamera();
        setCameraPreference('granted', storage);

        return 'granted';
    } catch {
        setCameraPreference('declined', storage);

        return 'blocked';
    }
}
