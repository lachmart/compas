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
