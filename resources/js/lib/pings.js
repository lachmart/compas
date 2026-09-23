import { distanceMeters } from './geo';

export const PING_MOVE_THRESHOLD_M = 5;
export const PING_HEARTBEAT_MS = 30000;

// Pure "should I ping now?" decision: no previous ping, moved at least
// PING_MOVE_THRESHOLD_M metres, or PING_HEARTBEAT_MS elapsed since the last
// ping — whichever comes first.
export function shouldSendPing(lastPing, position, nowMs) {
    if (!position) return false;
    if (!lastPing) return true;

    const moved = distanceMeters(lastPing.lat, lastPing.lng, position.lat, position.lng) >= PING_MOVE_THRESHOLD_M;
    const heartbeatDue = nowMs - lastPing.at >= PING_HEARTBEAT_MS;

    return moved || heartbeatDue;
}
