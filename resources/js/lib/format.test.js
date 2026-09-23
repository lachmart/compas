import { describe, expect, test } from 'vitest';
import { pluralize, relativeTime } from './format';

const now = Date.parse('2026-09-23T12:00:00Z');

describe('relativeTime', () => {
    test('empty for null', () => expect(relativeTime(null, now)).toBe(''));
    test('just now under a minute', () => expect(relativeTime('2026-09-23T11:59:30Z', now)).toBe('právě teď'));
    test('minutes', () => expect(relativeTime('2026-09-23T11:55:00Z', now)).toBe('před 5 min'));
    test('hours', () => expect(relativeTime('2026-09-23T09:00:00Z', now)).toBe('před 3 h'));
    test('yesterday', () => expect(relativeTime('2026-09-22T09:00:00Z', now)).toBe('včera'));
    test('older shows a date', () => expect(relativeTime('2026-09-03T09:00:00Z', now)).toBe('3. 9. 2026'));
});

describe('pluralize', () => {
    const s = (n) => pluralize(n, 'stanoviště', 'stanoviště', 'stanovišť');
    const h = (n) => pluralize(n, 'hráč', 'hráči', 'hráčů');
    test('one', () => expect(h(1)).toBe('1 hráč'));
    test('few', () => { expect(h(2)).toBe('2 hráči'); expect(h(4)).toBe('4 hráči'); });
    test('many and zero', () => { expect(h(5)).toBe('5 hráčů'); expect(h(0)).toBe('0 hráčů'); expect(s(12)).toBe('12 stanovišť'); });
});
