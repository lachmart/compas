import { expect, test } from 'vitest';
import { INACTIVE_AFTER_MS, inactiveIds, isInactive, reconcilePoll, splitPlayers } from './playerVisibility';

const now = Date.parse('2026-09-24T12:00:00Z');
const p = (id, o) => ({ id, finished_at: null, hidden: false, last_seen_at: '2026-09-24T11:59:30Z', ...o });
const seenAgo = (ms) => new Date(now - ms).toISOString();

test('inactive threshold is 30 minutes', () => {
    expect(INACTIVE_AFTER_MS).toBe(30 * 60 * 1000);
});

test('a player seen exactly 30 minutes ago is still active; a moment later inactive', () => {
    expect(isInactive(p(1, { last_seen_at: seenAgo(INACTIVE_AFTER_MS) }), now)).toBe(false);
    expect(isInactive(p(1, { last_seen_at: seenAgo(INACTIVE_AFTER_MS + 1000) }), now)).toBe(true);
    expect(isInactive(p(1), now)).toBe(false);
});

test('a finished player is inactive even if just seen', () => {
    expect(isInactive(p(1, { finished_at: '2026-09-24T11:59:00Z' }), now)).toBe(true);
});

test('splitPlayers separates hidden players, keeping order', () => {
    const players = [p(1), p(2, { hidden: true }), p(3), p(4, { hidden: true })];

    const { visible, hidden } = splitPlayers(players);

    expect(visible.map((x) => x.id)).toEqual([1, 3]);
    expect(hidden.map((x) => x.id)).toEqual([2, 4]);
});

test('inactiveIds lists visible inactive players only', () => {
    const players = [
        p(1),
        p(2, { finished_at: '2026-09-24T11:00:00Z' }),
        p(3, { last_seen_at: seenAgo(INACTIVE_AFTER_MS * 2) }),
        p(4, { last_seen_at: seenAgo(INACTIVE_AFTER_MS * 2), hidden: true }),
        p(5, { finished_at: '2026-09-24T11:00:00Z', hidden: true }),
    ];

    expect(inactiveIds(players, now)).toEqual([2, 3]);
});

test('reconcilePoll applies a fresh response and derives progress events', () => {
    const previous = [p(1)];
    const next = [p(1), p(2)];

    const { players, events } = reconcilePoll(previous, next, { stale: false, deletedIds: new Set() });

    expect(players.map((x) => x.id)).toEqual([1, 2]);
    expect(events).toEqual([{ kind: 'joined', player: next[1] }]);
});

test('reconcilePoll ignores a stale response (a local change landed meanwhile)', () => {
    const previous = [p(1, { hidden: true })];
    const next = [p(1, { hidden: false }), p(2)];

    const result = reconcilePoll(previous, next, { stale: true, deletedIds: new Set() });

    expect(result.players).toBe(previous);
    expect(result.events).toEqual([]);
});

test('reconcilePoll never re-adds a deleted player and raises no joined event', () => {
    const previous = [p(1)];
    const next = [p(1), p(2)];

    const { players, events } = reconcilePoll(previous, next, { stale: false, deletedIds: new Set([2]) });

    expect(players.map((x) => x.id)).toEqual([1]);
    expect(events).toEqual([]);
});
