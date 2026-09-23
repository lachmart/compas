import { expect, test } from 'vitest';
import { sortPlayers } from './playerOrder';

const now = Date.parse('2026-09-23T12:00:00Z');
const p = (id, o) => ({ id, current_location_index: 0, locations_count: 3, finished_at: null, last_seen_at: '2026-09-23T11:59:30Z', ...o });

test('active by progress, then stale by recency, then finished', () => {
    const players = [
        p(1, { finished_at: '2026-09-23T11:00:00Z' }),
        p(2, { last_seen_at: '2026-09-23T11:00:00Z' }),
        p(3, { current_location_index: 1 }),
        p(4, { current_location_index: 2 }),
        p(5, { last_seen_at: '2026-09-23T11:30:00Z' }),
    ];

    expect(sortPlayers(players, now).map((x) => x.id)).toEqual([4, 3, 5, 2, 1]);
    expect(players.map((x) => x.id)).toEqual([1, 2, 3, 4, 5]);
});

test('finished players: most recent finisher first', () => {
    const players = [
        p(1, { finished_at: '2026-09-23T10:00:00Z' }),
        p(2, { finished_at: '2026-09-23T11:00:00Z' }),
    ];

    expect(sortPlayers(players, now).map((x) => x.id)).toEqual([2, 1]);
});
