// The "how to play" help opens by itself on a player's first game on this
// device; afterwards only from their animal in the HUD.

const STORAGE_KEY = 'kompas.helpSeen';

export function helpSeen(storage = globalThis.localStorage) {
    try {
        return storage?.getItem(STORAGE_KEY) === '1';
    } catch {
        // Unreadable storage: don't pop the help up on every visit.
        return true;
    }
}

export function markHelpSeen(storage = globalThis.localStorage) {
    try {
        storage?.setItem(STORAGE_KEY, '1');
    } catch {
        // Private mode / blocked storage: nothing to remember it in.
    }
}
