import { afterEach, beforeEach, expect, test, vi } from 'vitest';
import { memoryStorage } from './testing/storage';

// The module keeps in-memory state (grant, in-flight request, listeners), so
// every test imports a fresh copy.
const load = async () => {
    vi.resetModules();

    return import('./orientationPermission');
};

const iosLike = (requestPermission) => {
    globalThis.DeviceOrientationEvent = class {};
    globalThis.DeviceOrientationEvent.requestPermission = requestPermission;
};

beforeEach(() => {
    globalThis.sessionStorage = memoryStorage();
});

afterEach(() => {
    delete globalThis.DeviceOrientationEvent;
    delete globalThis.sessionStorage;
});

test('without DeviceOrientationEvent there is nothing to ask for', async () => {
    const m = await load();

    expect(m.needsOrientationPermission()).toBe(false);
    expect(m.orientationGranted()).toBe(false);
    await expect(m.requestOrientationPermission()).resolves.toBe('unsupported');
});

test('a browser without requestPermission (Android) is granted without a prompt', async () => {
    globalThis.DeviceOrientationEvent = class {};
    const m = await load();

    expect(m.needsOrientationPermission()).toBe(false);
    expect(m.orientationGranted()).toBe(true);
    await expect(m.requestOrientationPermission()).resolves.toBe('granted');
});

test('requestPermission is called synchronously and a grant is remembered for the session', async () => {
    const requestPermission = vi.fn(() => Promise.resolve('granted'));
    iosLike(requestPermission);
    const m = await load();

    expect(m.needsOrientationPermission()).toBe(true);

    const pending = m.requestOrientationPermission();

    // Still inside the "click handler" – iOS only accepts the call there.
    expect(requestPermission).toHaveBeenCalledTimes(1);
    await expect(pending).resolves.toBe('granted');

    expect(m.orientationGranted()).toBe(true);
    expect(m.needsOrientationPermission()).toBe(false);
    expect(globalThis.sessionStorage.getItem('kompas.orientation')).toBe('granted');
});

test('a grant stored earlier in the session skips the prompt', async () => {
    const requestPermission = vi.fn(() => Promise.resolve('granted'));
    iosLike(requestPermission);
    globalThis.sessionStorage.setItem('kompas.orientation', 'granted');
    const m = await load();

    expect(m.needsOrientationPermission()).toBe(false);
    await expect(m.requestOrientationPermission()).resolves.toBe('granted');
    expect(requestPermission).not.toHaveBeenCalled();
});

test('a denial is not remembered, so a later tap can ask again', async () => {
    const requestPermission = vi.fn(() => Promise.resolve('denied'));
    iosLike(requestPermission);
    const m = await load();

    await expect(m.requestOrientationPermission()).resolves.toBe('denied');
    expect(m.needsOrientationPermission()).toBe(true);
    expect(globalThis.sessionStorage.getItem('kompas.orientation')).toBe(null);

    requestPermission.mockResolvedValueOnce('granted');
    await expect(m.requestOrientationPermission()).resolves.toBe('granted');
    expect(requestPermission).toHaveBeenCalledTimes(2);
});

test('a rejected request (no user gesture) counts as denied', async () => {
    iosLike(vi.fn(() => Promise.reject(new Error('NotAllowedError'))));
    const m = await load();

    await expect(m.requestOrientationPermission()).resolves.toBe('denied');
    expect(m.needsOrientationPermission()).toBe(true);
});

test('a synchronously throwing requestPermission counts as denied', async () => {
    iosLike(vi.fn(() => {
        throw new Error('boom');
    }));
    const m = await load();

    await expect(m.requestOrientationPermission()).resolves.toBe('denied');
});

test('concurrent requests share one prompt', async () => {
    let resolve;
    const requestPermission = vi.fn(() => new Promise((r) => (resolve = r)));
    iosLike(requestPermission);
    const m = await load();

    const first = m.requestOrientationPermission();
    const second = m.requestOrientationPermission();
    resolve('granted');

    await expect(first).resolves.toBe('granted');
    await expect(second).resolves.toBe('granted');
    expect(requestPermission).toHaveBeenCalledTimes(1);
});

test('listeners hear about a grant, and can unsubscribe', async () => {
    iosLike(vi.fn(() => Promise.resolve('granted')));
    const m = await load();
    const heard = vi.fn();
    const ignored = vi.fn();

    m.onOrientationGranted(heard);
    m.onOrientationGranted(ignored)();
    await m.requestOrientationPermission();

    expect(heard).toHaveBeenCalledTimes(1);
    expect(ignored).not.toHaveBeenCalled();
});

test('works when sessionStorage throws (private mode) by remembering in memory', async () => {
    globalThis.sessionStorage = {
        getItem: () => {
            throw new Error('SecurityError');
        },
        setItem: () => {
            throw new Error('SecurityError');
        },
    };
    const requestPermission = vi.fn(() => Promise.resolve('granted'));
    iosLike(requestPermission);
    const m = await load();

    expect(m.needsOrientationPermission()).toBe(true);
    await expect(m.requestOrientationPermission()).resolves.toBe('granted');
    expect(m.orientationGranted()).toBe(true);
    expect(m.needsOrientationPermission()).toBe(false);
});

test('works when sessionStorage is missing entirely', async () => {
    delete globalThis.sessionStorage;
    iosLike(vi.fn(() => Promise.resolve('granted')));
    const m = await load();

    await expect(m.requestOrientationPermission()).resolves.toBe('granted');
    expect(m.orientationGranted()).toBe(true);
});

test('force asks the browser even when a grant is remembered, and a failure keeps the grant', async () => {
    const requestPermission = vi.fn(() => Promise.reject(new Error('NotAllowedError')));
    iosLike(requestPermission);
    globalThis.sessionStorage.setItem('kompas.orientation', 'granted');
    const m = await load();

    const pending = m.requestOrientationPermission({ force: true });
    expect(requestPermission).toHaveBeenCalledTimes(1);
    await expect(pending).resolves.toBe('denied');

    expect(m.orientationGranted()).toBe(true);
    expect(globalThis.sessionStorage.getItem('kompas.orientation')).toBe('granted');
});

test('force without a permission API is simply granted', async () => {
    globalThis.DeviceOrientationEvent = class {};
    const m = await load();

    await expect(m.requestOrientationPermission({ force: true })).resolves.toBe('granted');
});

test('shouldShowTap only when gated, no event seen and the watchdog elapsed', async () => {
    const { shouldShowTap, ORIENTATION_WATCHDOG_MS } = await load();

    expect(shouldShowTap({ hasRequestPermission: true, eventSeen: false, elapsedMs: ORIENTATION_WATCHDOG_MS })).toBe(true);
    expect(shouldShowTap({ hasRequestPermission: true, eventSeen: false, elapsedMs: ORIENTATION_WATCHDOG_MS - 1 })).toBe(false);
    expect(shouldShowTap({ hasRequestPermission: true, eventSeen: true, elapsedMs: 10_000 })).toBe(false);
    expect(shouldShowTap({ hasRequestPermission: false, eventSeen: false, elapsedMs: 10_000 })).toBe(false);
    expect(shouldShowTap({ hasRequestPermission: true, eventSeen: false, elapsedMs: 100, timeoutMs: 50 })).toBe(true);
});

test('a forced request (a tap) does not reuse a pending non-gesture request', async () => {
    const resolvers = [];
    const requestPermission = vi.fn(() => new Promise((r) => resolvers.push(r)));
    iosLike(requestPermission);
    const m = await load();

    const quiet = m.requestOrientationPermission();
    expect(requestPermission).toHaveBeenCalledTimes(1);

    const tap = m.requestOrientationPermission({ force: true });
    // Fresh call inside the "gesture", not the pending one.
    expect(requestPermission).toHaveBeenCalledTimes(2);

    resolvers[0]('denied');
    await expect(quiet).resolves.toBe('denied');

    // The settled quiet request must not clear the tap's in-flight request:
    // a non-forced call now still joins the tap's prompt.
    const joined = m.requestOrientationPermission();
    expect(requestPermission).toHaveBeenCalledTimes(2);

    resolvers[1]('granted');
    await expect(tap).resolves.toBe('granted');
    await expect(joined).resolves.toBe('granted');
});
