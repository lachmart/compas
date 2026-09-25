import { readStorage, writeStorage } from '@/lib/safeStorage';

// The "how to play" help opens by itself on a player's first game on this
// device; afterwards only from their animal in the HUD.

const STORAGE_KEY = 'kompas.helpSeen';

// Unreadable storage counts as seen: better than popping up every visit.
export function helpSeen(storage = globalThis.localStorage) {
    return readStorage(storage, STORAGE_KEY, '1') === '1';
}

export function markHelpSeen(storage = globalThis.localStorage) {
    writeStorage(storage, STORAGE_KEY, '1');
}
