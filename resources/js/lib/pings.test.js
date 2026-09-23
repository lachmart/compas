import { describe, expect, it } from 'vitest';
import { PING_HEARTBEAT_MS, PING_MOVE_THRESHOLD_M, shouldSendPing } from './pings';

const position = (overrides = {}) => ({ lat: 50.08, lng: 14.43, ...overrides });

describe('shouldSendPing', () => {
    it('is false without a current position', () => {
        expect(shouldSendPing(null, null, Date.now())).toBe(false);
    });

    it('is true when there is no previous ping', () => {
        expect(shouldSendPing(null, position(), Date.now())).toBe(true);
    });

    it('is true once moved at least the threshold distance', () => {
        const last = { lat: 50.08, lng: 14.43, at: Date.now() };
        // ~6m east at this latitude.
        const moved = position({ lng: 14.43 + 0.00009 });

        expect(shouldSendPing(last, moved, last.at + 1000)).toBe(true);
    });

    it('is false when barely moved and heartbeat not yet due', () => {
        const last = { lat: 50.08, lng: 14.43, at: Date.now() };
        const barelyMoved = position({ lng: 14.43 + 0.000001 });

        expect(shouldSendPing(last, barelyMoved, last.at + 1000)).toBe(false);
    });

    it('is true once the heartbeat interval has elapsed even without movement', () => {
        const last = { lat: 50.08, lng: 14.43, at: Date.now() };

        expect(shouldSendPing(last, position(), last.at + PING_HEARTBEAT_MS)).toBe(true);
    });

    it('is false just before the heartbeat interval elapses without movement', () => {
        const last = { lat: 50.08, lng: 14.43, at: Date.now() };

        expect(shouldSendPing(last, position(), last.at + PING_HEARTBEAT_MS - 1)).toBe(false);
    });

    it('exports the constants used for the thresholds', () => {
        expect(PING_MOVE_THRESHOLD_M).toBe(5);
        expect(PING_HEARTBEAT_MS).toBe(30000);
    });
});
