import { describe, expect, test } from 'vitest';
import {
    BEHIND_DEG,
    ON_COURSE_DEG,
    UPRIGHT_ENTER_DEG,
    UPRIGHT_EXIT_DEG,
    absoluteOrientation,
    cameraHeading,
    nextPosture,
    tiltDegrees,
    turnGuidance,
} from './orientation';

describe('cameraHeading', () => {
    // Phone held upright (beta 90). With alpha 0 the screen faces south,
    // so the back camera looks north.
    test('upright facing north', () => {
        expect(cameraHeading(0, 90, 0)).toBeCloseTo(0, 5);
    });

    test('upright facing east', () => {
        expect(cameraHeading(270, 90, 0)).toBeCloseTo(90, 5);
    });

    test('upright facing south', () => {
        expect(cameraHeading(180, 90, 0)).toBeCloseTo(180, 5);
    });

    test('upright facing west', () => {
        expect(cameraHeading(90, 90, 0)).toBeCloseTo(270, 5);
    });

    test('wraps just west of north into the 350s', () => {
        expect(cameraHeading(10, 90, 0)).toBeCloseTo(350, 5);
    });

    test('gamma turns the camera direction when upright', () => {
        expect(cameraHeading(0, 90, 30)).toBeCloseTo(330, 5);
    });

    test('always returns a value in [0, 360)', () => {
        for (const alpha of [0, 45, 90, 135, 180, 225, 270, 315, 359.9]) {
            const heading = cameraHeading(alpha, 80, -10);
            expect(heading).toBeGreaterThanOrEqual(0);
            expect(heading).toBeLessThan(360);
        }
    });
});

describe('tiltDegrees', () => {
    test('flat screen-up is 0', () => {
        expect(tiltDegrees(0, 0)).toBeCloseTo(0, 5);
    });

    test('upright is 90', () => {
        expect(tiltDegrees(90, 0)).toBeCloseTo(90, 5);
    });

    test('on its side counts as upright too', () => {
        expect(tiltDegrees(0, 90)).toBeCloseTo(90, 5);
    });

    test('partial forward tilt', () => {
        expect(tiltDegrees(60, 0)).toBeCloseTo(60, 5);
    });
});

describe('nextPosture', () => {
    test('stays flat until tilt passes the enter threshold', () => {
        expect(nextPosture('flat', UPRIGHT_ENTER_DEG - 1, 0)).toBe('flat');
        expect(nextPosture('flat', UPRIGHT_ENTER_DEG + 1, 0)).toBe('upright');
    });

    test('stays upright until tilt drops below the exit threshold', () => {
        expect(nextPosture('upright', UPRIGHT_EXIT_DEG + 1, 0)).toBe('upright');
        expect(nextPosture('upright', UPRIGHT_EXIT_DEG - 1, 0)).toBe('flat');
    });

    test('keeps the previous posture inside the hysteresis band', () => {
        expect(nextPosture('flat', 45, 0)).toBe('flat');
        expect(nextPosture('upright', 45, 0)).toBe('upright');
    });
});

describe('turnGuidance', () => {
    test('on course within the tolerance on either side', () => {
        expect(turnGuidance(100, 100)).toBe('on_course');
        expect(turnGuidance(100, 100 - ON_COURSE_DEG)).toBe('on_course');
        expect(turnGuidance(100, 100 + ON_COURSE_DEG)).toBe('on_course');
    });

    test('target clockwise of the camera means turn right', () => {
        expect(turnGuidance(100, 50)).toBe('right');
    });

    test('target counter-clockwise of the camera means turn left', () => {
        expect(turnGuidance(50, 100)).toBe('left');
    });

    test('target behind means turn around', () => {
        expect(turnGuidance(0, 180)).toBe('behind');
        expect(turnGuidance(0, BEHIND_DEG + 1)).toBe('behind');
        expect(turnGuidance(0, 360 - BEHIND_DEG - 1)).toBe('behind');
    });

    test('just inside the behind threshold is still a turn', () => {
        expect(turnGuidance(0, 360 - BEHIND_DEG + 1)).toBe('right');
        expect(turnGuidance(0, BEHIND_DEG - 1)).toBe('left');
    });

    test('wraps around north', () => {
        expect(turnGuidance(350, 40)).toBe('left');
        expect(turnGuidance(40, 350)).toBe('right');
        expect(turnGuidance(355, 5)).toBe('on_course');
    });
});

describe('absoluteOrientation', () => {
    test('uses an absolute event as-is', () => {
        expect(absoluteOrientation({ absolute: true, alpha: 30, beta: 80, gamma: 5 }))
            .toEqual({ alpha: 30, beta: 80, gamma: 5 });
    });

    test('derives alpha from the iOS compass heading', () => {
        expect(absoluteOrientation({ absolute: false, alpha: 123, beta: 80, gamma: 5, webkitCompassHeading: 90 }))
            .toEqual({ alpha: 270, beta: 80, gamma: 5 });
    });

    test('maps an iOS heading of 0 to alpha 0, not 360', () => {
        expect(absoluteOrientation({ alpha: 1, beta: 0, gamma: 0, webkitCompassHeading: 0 }).alpha).toBe(0);
    });

    test('ignores relative events without a compass heading', () => {
        expect(absoluteOrientation({ absolute: false, alpha: 30, beta: 80, gamma: 5 })).toBeNull();
    });

    test('ignores events with missing angles', () => {
        expect(absoluteOrientation({ absolute: true, alpha: null, beta: null, gamma: null })).toBeNull();
    });
});
