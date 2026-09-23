import { describe, expect, it } from 'vitest';
import {
    STALE_AFTER_MS,
    applyPosition,
    applyProgress,
    diffProgress,
    isStale,
    progressLabel,
    progressMessage,
} from './tracking';

const player = (overrides = {}) => ({
    id: 1,
    nickname: 'Anna',
    color: '#e6194b',
    current_location_index: 2,
    locations_count: 8,
    finished_at: null,
    last_seen_at: '2026-09-23T10:00:00+00:00',
    position: null,
    ...overrides,
});

describe('isStale', () => {
    const seen = Date.parse('2026-09-23T10:00:00+00:00');

    it('is fresh right up to the threshold', () => {
        expect(isStale(player(), seen + STALE_AFTER_MS)).toBe(false);
    });

    it('is stale after the threshold', () => {
        expect(isStale(player(), seen + STALE_AFTER_MS + 1)).toBe(true);
    });

    it('is never stale once finished', () => {
        expect(isStale(player({ finished_at: '2026-09-23T10:00:00+00:00' }), seen + 10 * STALE_AFTER_MS)).toBe(false);
    });
});

describe('progressLabel', () => {
    it('shows the 1-based current location out of the total', () => {
        expect(progressLabel(player())).toBe('3/8');
    });

    it('shows a check mark when finished', () => {
        expect(progressLabel(player({ finished_at: 'x' }))).toBe('✓ 8/8');
    });
});

describe('applyPosition', () => {
    it('updates position and last seen of the matching player immutably', () => {
        const players = [player(), player({ id: 2 })];

        const next = applyPosition(players, {
            player_session_id: 2, lat: 50.1, lng: 14.4, heading: 90, last_seen_at: '2026-09-23T10:05:00+00:00',
        });

        expect(next).not.toBe(players);
        expect(next[1].position).toEqual({ lat: 50.1, lng: 14.4, heading: 90 });
        expect(next[1].last_seen_at).toBe('2026-09-23T10:05:00+00:00');
        expect(next[0]).toBe(players[0]);
    });

    it('ignores unknown players', () => {
        const players = [player()];

        expect(applyPosition(players, { player_session_id: 99, lat: 1, lng: 1, heading: null, last_seen_at: 'x' })).toEqual(players);
    });
});

describe('applyProgress', () => {
    it('replaces an existing player', () => {
        const next = applyProgress([player()], { kind: 'completed_location', player: player({ current_location_index: 3 }) });

        expect(next).toHaveLength(1);
        expect(next[0].current_location_index).toBe(3);
    });

    it('appends a newly joined player', () => {
        const next = applyProgress([player()], { kind: 'joined', player: player({ id: 2, nickname: 'Bořek' }) });

        expect(next.map((p) => p.nickname)).toEqual(['Anna', 'Bořek']);
    });
});

describe('progressMessage', () => {
    it('describes each kind of progress in Czech', () => {
        expect(progressMessage({ kind: 'joined', player: player() })).toBe('Anna se připojil(a) ke hře');
        expect(progressMessage({ kind: 'completed_location', player: player({ current_location_index: 3 }) }))
            .toBe('Anna splnil(a) stanoviště 3/8');
        expect(progressMessage({ kind: 'finished', player: player() })).toBe('Anna dokončil(a) hru 🎉');
    });
});

describe('diffProgress', () => {
    it('reports a newly joined player', () => {
        const events = diffProgress([player()], [player(), player({ id: 2, nickname: 'Bořek' })]);

        expect(events).toEqual([{ kind: 'joined', player: player({ id: 2, nickname: 'Bořek' }) }]);
    });

    it('reports a completed location when the index moves forward', () => {
        const next = player({ current_location_index: 3 });

        expect(diffProgress([player()], [next])).toEqual([{ kind: 'completed_location', player: next }]);
    });

    it('reports finished instead of completed_location for the final location', () => {
        const next = player({ finished_at: '2026-09-23T11:00:00+00:00' });

        expect(diffProgress([player()], [next])).toEqual([{ kind: 'finished', player: next }]);
    });

    it('reports nothing when only position or last seen changed', () => {
        const next = player({ position: { lat: 1, lng: 2, heading: null }, last_seen_at: '2026-09-23T10:05:00+00:00' });

        expect(diffProgress([player()], [next])).toEqual([]);
    });

    it('does not re-report an already finished player', () => {
        const done = player({ finished_at: '2026-09-23T11:00:00+00:00' });

        expect(diffProgress([done], [done])).toEqual([]);
    });
});
