import { describe, expect, test } from 'vitest';
import {
    AIM_DEG,
    AIM_EXIT_DEG,
    AR_FOV_DEG,
    BEHIND_DEG,
    UPRIGHT_ENTER_DEG,
    UPRIGHT_EXIT_DEG,
    absoluteOrientation,
    aimTarget,
    arCameraHeading,
    arTargetSize,
    easeTowards,
    AR_TARGET_FAR,
    AR_TARGET_NEAR,
    angleDiff,
    relativeOrientation,
    cameraHeading,
    nextPosture,
    smoothAngle,
    tiltDegrees,
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

    test('realistic forward tilt facing north', () => {
        expect(cameraHeading(0, 60, 0)).toBeCloseTo(0, 5);
    });

    test('alpha/gamma split at vertical only their sum matters', () => {
        expect(cameraHeading(20, 90, -20)).toBeCloseTo(0, 5);
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
        expect(nextPosture('flat', 60, 0)).toBe('flat');
        expect(nextPosture('upright', 60, 0)).toBe('upright');
    });

    test('a moderate tilt while walking keeps the flat compass', () => {
        expect(nextPosture('flat', 55, 0)).toBe('flat');
        expect(nextPosture('flat', 65, 10)).toBe('flat');
    });

    // Exact thresholds are flaky: tiltDegrees round-trips through cos/acos
    // (70 -> 70.00000000000001). Just inside keeps the assertion stable.
    test('near-exact thresholds keep the current posture', () => {
        expect(nextPosture('flat', UPRIGHT_ENTER_DEG - 0.1, 0)).toBe('flat');
        expect(nextPosture('upright', UPRIGHT_EXIT_DEG + 0.1, 0)).toBe('upright');
    });
});

describe('angleDiff', () => {
    test('signed shortest turn, clockwise positive', () => {
        expect(angleDiff(0, 30)).toBe(30);
        expect(angleDiff(30, 0)).toBe(-30);
        expect(angleDiff(350, 10)).toBe(20);
        expect(angleDiff(10, 350)).toBe(-20);
        expect(angleDiff(0, 180)).toBe(180);
    });
});

describe('aimTarget', () => {
    const half = AR_FOV_DEG / 2;

    test('straight ahead is centred and aimed', () => {
        expect(aimTarget(100, 100)).toEqual({ offset: 0, direction: null, aligned: true });
    });

    test('inside the view the target slides with the angle', () => {
        expect(aimTarget(100 + half / 2, 100).offset).toBeCloseTo(0.5);
        expect(aimTarget(100 - half, 100).offset).toBeCloseTo(-1);
        expect(aimTarget(100 + half, 100).direction).toBeNull();
    });

    test('outside the view it points the way to turn', () => {
        expect(aimTarget(100 + half + 1, 100)).toMatchObject({ offset: null, direction: 'right' });
        expect(aimTarget(100 - half - 1, 100)).toMatchObject({ offset: null, direction: 'left' });
    });

    test('far behind means turn around', () => {
        expect(aimTarget(0, 180).direction).toBe('behind');
        expect(aimTarget(0, BEHIND_DEG + 1).direction).toBe('behind');
        expect(aimTarget(0, BEHIND_DEG).direction).toBe('left');
    });

    test('wraps around north', () => {
        expect(aimTarget(355, 5).offset).toBeCloseTo(-10 / half);
        expect(aimTarget(5, 355).offset).toBeCloseTo(10 / half);
    });

    test('aiming has hysteresis', () => {
        const between = (AIM_DEG + AIM_EXIT_DEG) / 2;

        expect(aimTarget(AIM_DEG, 0).aligned).toBe(true);
        expect(aimTarget(between, 0, false).aligned).toBe(false);
        expect(aimTarget(between, 0, true).aligned).toBe(true);
        expect(aimTarget(AIM_EXIT_DEG + 1, 0, true).aligned).toBe(false);
    });
});

describe('smoothAngle', () => {
    test('starts at the first reading', () => {
        expect(smoothAngle(null, 42, 0.2)).toBe(42);
    });

    test('moves part of the way towards the new reading', () => {
        expect(smoothAngle(0, 100, 0.25)).toBeCloseTo(25);
    });

    test('takes the short way round north', () => {
        expect(smoothAngle(350, 10, 0.5)).toBeCloseTo(0);
        expect(smoothAngle(10, 350, 0.5)).toBeCloseTo(0);
    });
});

describe('absoluteOrientation', () => {
    test('uses an absolute event as-is', () => {
        expect(absoluteOrientation({ absolute: true, alpha: 30, beta: 80, gamma: 5 }))
            .toEqual({ alpha: 30, beta: 80, gamma: 5 });
    });

    test('derives alpha from the iOS compass heading', () => {
        expect(absoluteOrientation({ absolute: false, alpha: 123, beta: 80, gamma: 5, webkitCompassHeading: 90 }))
            .toEqual({ alpha: 270, beta: 80, gamma: 5, compassHeading: 90 });
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

describe('relativeOrientation', () => {
    test('uses the relative angles as-is', () => {
        expect(relativeOrientation({ absolute: false, alpha: 37.9, beta: 92.1, gamma: -19.6 }))
            .toEqual({ alpha: 37.9, beta: 92.1, gamma: -19.6 });
    });

    test('ignores events with missing angles (e.g. a desktop absolute event full of nulls)', () => {
        expect(relativeOrientation({ absolute: true, alpha: null, beta: null, gamma: null })).toBeNull();
        expect(relativeOrientation({ alpha: 10, beta: null, gamma: 5 })).toBeNull();
    });
});

describe('arTargetSize', () => {
    test('small far away, big close by', () => {
        expect(arTargetSize(AR_TARGET_FAR.meters)).toBe(AR_TARGET_FAR.px);
        expect(arTargetSize(AR_TARGET_NEAR.meters)).toBe(AR_TARGET_NEAR.px);
    });

    test('clamped beyond both ends', () => {
        expect(arTargetSize(5000)).toBe(AR_TARGET_FAR.px);
        expect(arTargetSize(2)).toBe(AR_TARGET_NEAR.px);
    });

    test('grows the most over the last metres (log scale)', () => {
        const mid = arTargetSize(Math.sqrt(AR_TARGET_FAR.meters * AR_TARGET_NEAR.meters));
        expect(mid).toBe(Math.round((AR_TARGET_FAR.px + AR_TARGET_NEAR.px) / 2));
        expect(arTargetSize(100)).toBeLessThan(arTargetSize(50));
    });

    test('unknown distance gets a middle size', () => {
        expect(arTargetSize(null)).toBe(80);
    });
});

describe('easeTowards', () => {
    test('starts at the target', () => {
        expect(easeTowards(null, 0.4, 16, 120)).toBe(0.4);
    });

    test('covers ~63 % of the gap in one time constant', () => {
        expect(easeTowards(0, 1, 120, 120)).toBeCloseTo(1 - Math.exp(-1));
    });

    test('the same motion at any frame rate', () => {
        let at60 = 0;
        for (let i = 0; i < 6; i++) at60 = easeTowards(at60, 1, 1000 / 60, 120);
        const at30 = easeTowards(easeTowards(easeTowards(0, 1, 1000 / 30, 120), 1, 1000 / 30, 120), 1, 1000 / 30, 120);

        expect(at60).toBeCloseTo(at30);
    });
});

describe('arCameraHeading', () => {
    test('iOS: the compass heading as reported, whatever beta/gamma do near vertical', () => {
        // gamma flipped by iOS as the phone passes vertical must not matter.
        expect(arCameraHeading({ alpha: 270, beta: 91, gamma: -178, compassHeading: 90 })).toBe(90);
        expect(arCameraHeading({ alpha: 270, beta: 89, gamma: 2, compassHeading: 90 })).toBe(90);
    });

    test('other browsers: the camera direction from the Euler angles', () => {
        expect(arCameraHeading({ alpha: 0, beta: 90, gamma: 0 })).toBeCloseTo(0);
        expect(arCameraHeading({ alpha: 270, beta: 90, gamma: 0 })).toBeCloseTo(90);
    });
});
