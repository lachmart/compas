export const STALE_AFTER_MS = 120000;

export function isStale(player, nowMs) {
    if (player.finished_at) return false;

    return nowMs - Date.parse(player.last_seen_at) > STALE_AFTER_MS;
}

export function progressLabel(player) {
    if (player.finished_at) return `✓ ${player.locations_count}/${player.locations_count}`;

    return `${player.current_location_index + 1}/${player.locations_count}`;
}

export function applyPosition(players, payload) {
    return players.map((player) =>
        player.id === payload.player_session_id
            ? {
                  ...player,
                  position: { lat: payload.lat, lng: payload.lng, heading: payload.heading },
                  last_seen_at: payload.last_seen_at,
              }
            : player,
    );
}

export function applyProgress(players, payload) {
    const exists = players.some((player) => player.id === payload.player.id);

    if (!exists) return [...players, payload.player];

    return players.map((player) => (player.id === payload.player.id ? payload.player : player));
}

// Derives the same { kind, player } events the broadcast path delivers, by
// comparing two polled snapshots of the player list.
export function diffProgress(previous, next) {
    const previousById = new Map(previous.map((player) => [player.id, player]));

    return next.flatMap((player) => {
        const before = previousById.get(player.id);

        if (!before) return [{ kind: 'joined', player }];
        if (player.finished_at && !before.finished_at) return [{ kind: 'finished', player }];
        if (player.current_location_index > before.current_location_index) {
            return [{ kind: 'completed_location', player }];
        }

        return [];
    });
}

export function progressMessage({ kind, player }) {
    if (kind === 'joined') return `${player.nickname} se připojil(a) ke hře`;
    if (kind === 'finished') return `${player.nickname} dokončil(a) hru 🎉`;

    return `${player.nickname} splnil(a) stanoviště ${player.current_location_index}/${player.locations_count}`;
}

export function formatTime(iso) {
    return new Date(iso).toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });
}
