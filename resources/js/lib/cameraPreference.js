// Whether the player chose to play with the camera (AR aim) when the join
// screen explained the permissions. 'declined' means the game must never
// ask for the camera by surprise later; null = not decided yet.

const STORAGE_KEY = 'kompas.camera';

export function cameraPreference(storage = globalThis.localStorage) {
    try {
        const value = storage?.getItem(STORAGE_KEY);

        return value === 'granted' || value === 'declined' ? value : null;
    } catch {
        return null;
    }
}

export function setCameraPreference(value, storage = globalThis.localStorage) {
    try {
        storage?.setItem(STORAGE_KEY, value);
    } catch {
        // Private mode / blocked storage: the choice just isn't remembered.
    }
}
