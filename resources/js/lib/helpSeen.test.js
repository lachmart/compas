import { expect, test } from 'vitest';
import { helpSeen, markHelpSeen } from './helpSeen';

const memoryStorage = () => {
    const data = new Map();

    return { getItem: (key) => data.get(key) ?? null, setItem: (key, value) => data.set(key, String(value)) };
};

test('the help is new until it has been seen', () => {
    const storage = memoryStorage();

    expect(helpSeen(storage)).toBe(false);
    markHelpSeen(storage);
    expect(helpSeen(storage)).toBe(true);
});

test('broken storage never pops the help up again and again', () => {
    const broken = { getItem: () => { throw new Error('blocked'); }, setItem: () => { throw new Error('blocked'); } };

    expect(helpSeen(broken)).toBe(true);
    expect(() => markHelpSeen(broken)).not.toThrow();
});
