import { expect, test } from 'vitest';
import { readStorage, writeStorage } from './safeStorage';
import { brokenStorage, memoryStorage } from './testing/storage';

test('reads back what was written', () => {
    const storage = memoryStorage();

    expect(readStorage(storage, 'k')).toBeNull();
    writeStorage(storage, 'k', 'v');
    expect(readStorage(storage, 'k')).toBe('v');
});

test('a broken or missing storage never throws', () => {
    expect(readStorage(brokenStorage(), 'k', 'fallback')).toBe('fallback');
    expect(readStorage(undefined, 'k')).toBeNull();
    expect(() => writeStorage(brokenStorage(), 'k', 'v')).not.toThrow();
});
