import { expect, test } from 'vitest';
import { AVATARS, avatarEmoji } from './avatars';

test('twenty animals with emoji and Czech labels in a fixed order', () => {
    expect(AVATARS.map((a) => a.key)).toEqual([
        'fox', 'hedgehog', 'owl', 'rabbit', 'bear', 'frog', 'squirrel', 'deer',
        'wolf', 'boar', 'badger', 'mouse', 'bat', 'duck', 'turtle', 'snail', 'ladybug', 'bee', 'butterfly', 'lion',
    ]);
    expect(new Set(AVATARS.map((a) => a.emoji)).size).toBe(AVATARS.length);
    expect(AVATARS.find((a) => a.key === 'owl').label).toBe('Sova');
});

test('avatarEmoji falls back for unknown or missing keys', () => {
    expect(avatarEmoji('fox')).toBe('🦊');
    expect(avatarEmoji(null)).toBe('🙂');
    expect(avatarEmoji('dragon')).toBe('🙂');
});
