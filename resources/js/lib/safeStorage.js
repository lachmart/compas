// Web Storage access that never throws: private mode or blocked site data
// make getItem/setItem (or the storage object itself) fail, and the game
// must carry on without remembering.

// The stored string, null when missing, or `onError` when unreadable.
export function readStorage(storage, key, onError = null) {
    try {
        return storage?.getItem(key) ?? null;
    } catch {
        return onError;
    }
}

export function writeStorage(storage, key, value) {
    try {
        storage?.setItem(key, value);
    } catch {
        // Nothing to remember it in; the caller works without it.
    }
}
