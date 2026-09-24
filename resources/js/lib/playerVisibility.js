import { diffProgress } from './tracking';

// "Inactive" players are the ones the dozor can tidy away with one click:
// they finished the game or haven't been seen for half an hour.
export const INACTIVE_AFTER_MS = 30 * 60 * 1000;

export function isInactive(player, nowMs) {
    if (player.finished_at) return true;

    return nowMs - Date.parse(player.last_seen_at) > INACTIVE_AFTER_MS;
}

export function splitPlayers(players) {
    const visible = [];
    const hidden = [];

    players.forEach((player) => (player.hidden ? hidden : visible).push(player));

    return { visible, hidden };
}

export function inactiveIds(players, nowMs) {
    return players.filter((player) => !player.hidden && isInactive(player, nowMs)).map((player) => player.id);
}

// Applies a polled player list. A response is `stale` when a local hide/
// unhide/delete succeeded while it was in flight — it may predate that change,
// so it is dropped and the next poll applies instead. Deleted players can
// never come back (a rejoin gets a new id), so they are filtered out before
// diffing — no false "joined" toast.
export function reconcilePoll(previous, next, { stale, deletedIds }) {
    if (stale) return { players: previous, events: [] };

    const players = next.filter((player) => !deletedIds.has(player.id));

    return { players, events: diffProgress(previous, players) };
}
