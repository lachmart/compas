import { expect, test } from 'vitest';
import { AVATARS, avatarEmoji } from './avatars';

test('eight animals with emoji and Czech labels in a fixed order', () => {
    expect(AVATARS.map((a) => a.key)).toEqual(['fox', 'hedgehog', 'owl', 'rabbit', 'bear', 'frog', 'squirrel', 'deer']);
    expect(AVATARS.find((a) => a.key === 'owl').label).toBe('Sova');
});

test('avatarEmoji falls back for unknown or missing keys', () => {
    expect(avatarEmoji('fox')).toBe('🦊');
    expect(avatarEmoji(null)).toBe('🙂');
    expect(avatarEmoji('dragon')).toBe('🙂');
});
