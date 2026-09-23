import { expect, test } from 'vitest';
import { gameStatusLabel, gameStatusTone } from './gameStatus';

test('labels and tones', () => {
    expect(gameStatusLabel('draft')).toBe('koncept');
    expect(gameStatusLabel('published')).toBe('publikováno');
    expect(gameStatusLabel('archived')).toBe('archivováno');
    expect(gameStatusTone('published')).toBe('green');
    expect(gameStatusTone('draft')).toBe('slate');
    expect(gameStatusTone('archived')).toBe('amber');
});
