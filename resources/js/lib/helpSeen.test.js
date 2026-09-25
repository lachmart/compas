import { expect, test } from 'vitest';
import { helpSeen, markHelpSeen } from './helpSeen';
import { brokenStorage, memoryStorage } from './testing/storage';

test('the help is new until it has been seen', () => {
    const storage = memoryStorage();

    expect(helpSeen(storage)).toBe(false);
    markHelpSeen(storage);
    expect(helpSeen(storage)).toBe(true);
});

test('broken storage never pops the help up again and again', () => {
    const broken = brokenStorage();

    expect(helpSeen(broken)).toBe(true);
    expect(() => markHelpSeen(broken)).not.toThrow();
});
