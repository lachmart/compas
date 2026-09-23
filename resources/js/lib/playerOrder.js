import { isStale } from './tracking';

// 0 = playing, 1 = no signal (stale), 2 = finished.
const group = (player, nowMs) => (player.finished_at ? 2 : isStale(player, nowMs) ? 1 : 0);

// Panel order: active players by progress (furthest first), then stale ones
// by last contact (most recent first), finished ones at the end (latest
// finisher first). Returns a new array.
export function sortPlayers(players, nowMs) {
    return [...players].sort((a, b) => {
        const ga = group(a, nowMs);
        const gb = group(b, nowMs);
        if (ga !== gb) return ga - gb;
        if (ga === 0) return b.current_location_index - a.current_location_index;
        if (ga === 1) return Date.parse(b.last_seen_at) - Date.parse(a.last_seen_at);

        return Date.parse(b.finished_at) - Date.parse(a.finished_at);
    });
}
