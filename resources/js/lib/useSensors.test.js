import { afterEach, beforeEach, expect, test, vi } from 'vitest';

// Drives useSensors' permission/watchdog logic outside a component (Vue only
// warns about onUnmounted there). window is a plain EventTarget stand-in.
let target;

const orientationEvent = (angles) => Object.assign(new Event('deviceorientation'), angles);

const setup = async ({ requestPermission, remembered = false } = {}) => {
    vi.resetModules();

    const DOE = class {};
    if (requestPermission) DOE.requestPermission = requestPermission;

    target = new EventTarget();
    globalThis.DeviceOrientationEvent = DOE;
    globalThis.window = {
        DeviceOrientationEvent: DOE,
        addEventListener: target.addEventListener.bind(target),
        removeEventListener: target.removeEventListener.bind(target),
    };
    const store = new Map(remembered ? [['kompas.orientation', 'granted']] : []);
    globalThis.sessionStorage = {
        getItem: (k) => store.get(k) ?? null,
        setItem: (k, v) => store.set(k, v),
    };

    const { useSensors } = await import('./useSensors');

    return useSensors();
};

const flush = () => vi.advanceTimersByTimeAsync(0);

beforeEach(() => {
    vi.useFakeTimers();
    vi.spyOn(console, 'warn').mockImplementation(() => {});
});

afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    delete globalThis.DeviceOrientationEvent;
    delete globalThis.window;
    delete globalThis.sessionStorage;
});

test('remembered grant but no events after a reload: re-asks quietly, then shows the tap', async () => {
    const requestPermission = vi.fn(() => Promise.reject(new Error('NotAllowedError')));
    const sensors = await setup({ requestPermission, remembered: true });

    sensors.requestPermissions();
    expect(requestPermission).toHaveBeenCalledTimes(1);
    await flush();
    expect(sensors.needsTap.value).toBe(false);

    await vi.advanceTimersByTimeAsync(1500);
    expect(sensors.needsTap.value).toBe(true);
});

test('remembered grant with events flowing never shows the tap', async () => {
    const sensors = await setup({ requestPermission: vi.fn(() => Promise.resolve('granted')), remembered: true });

    sensors.requestPermissions();
    await flush();
    target.dispatchEvent(orientationEvent({ alpha: 10, beta: 5, gamma: 1 }));
    await vi.advanceTimersByTimeAsync(5000);

    expect(sensors.needsTap.value).toBe(false);
    expect(sensors.heading.value).toBe(350);
});

test('events with null angles (no real sensor) do not count', async () => {
    const sensors = await setup({ requestPermission: vi.fn(() => Promise.resolve('granted')), remembered: true });

    sensors.requestPermissions();
    target.dispatchEvent(orientationEvent({ alpha: null, beta: null, gamma: null }));
    await vi.advanceTimersByTimeAsync(1500);

    expect(sensors.needsTap.value).toBe(true);
});

test('no grant and a rejected request outside a gesture shows the tap at once', async () => {
    const sensors = await setup({ requestPermission: vi.fn(() => Promise.reject(new Error('NotAllowedError'))) });

    await sensors.requestPermissions();

    expect(sensors.needsTap.value).toBe(true);
    expect(sensors.orientationSupported.value).toBe(false);
});

test('enableCompass asks synchronously, re-listens and the first event hides the tap', async () => {
    const requestPermission = vi.fn(() => Promise.reject(new Error('NotAllowedError')));
    const sensors = await setup({ requestPermission, remembered: true });

    sensors.requestPermissions();
    await vi.advanceTimersByTimeAsync(1500);
    expect(sensors.needsTap.value).toBe(true);

    requestPermission.mockImplementation(() => Promise.resolve('granted'));
    const pending = sensors.enableCompass();
    expect(requestPermission).toHaveBeenCalledTimes(2);
    await pending;
    expect(sensors.orientationSupported.value).toBe(true);

    target.dispatchEvent(orientationEvent({ alpha: 0, beta: 5, gamma: 1 }));
    expect(sensors.needsTap.value).toBe(false);
});

test('without a permission API (Android) there is never a tap', async () => {
    const sensors = await setup();

    sensors.requestPermissions();
    await vi.advanceTimersByTimeAsync(5000);

    expect(sensors.needsTap.value).toBe(false);
});

test('a tap that ends in a denial stops offering the pulsing tap', async () => {
    const requestPermission = vi.fn(() => Promise.reject(new Error('NotAllowedError')));
    const sensors = await setup({ requestPermission });

    await sensors.requestPermissions();
    expect(sensors.needsTap.value).toBe(true);
    expect(sensors.compassDenied.value).toBe(false);

    requestPermission.mockImplementation(() => Promise.resolve('denied'));
    await sensors.enableCompass();

    expect(sensors.needsTap.value).toBe(false);
    expect(sensors.compassDenied.value).toBe(true);
    expect(sensors.orientationSupported.value).toBe(false);
});

// --- Shared streams (battery) and pausing while the page is hidden -------------

const withGeolocation = () => {
    const geolocation = { watchPosition: vi.fn(() => 7), clearWatch: vi.fn() };
    vi.stubGlobal('navigator', { geolocation });

    return geolocation;
};

const withDocument = () => {
    const doc = Object.assign(new EventTarget(), { visibilityState: 'visible' });
    vi.stubGlobal('document', doc);

    return {
        hide: () => {
            doc.visibilityState = 'hidden';
            doc.dispatchEvent(new Event('visibilitychange'));
        },
        show: () => {
            doc.visibilityState = 'visible';
            doc.dispatchEvent(new Event('visibilitychange'));
        },
    };
};

test('every screen shares one geolocation watch', async () => {
    const geolocation = withGeolocation();
    const first = await setup();
    const { useSensors } = await import('./useSensors');
    const second = useSensors();

    first.requestPermissions();
    second.requestPermissions();

    expect(geolocation.watchPosition).toHaveBeenCalledTimes(1);
});

test('a hidden page stops the sensors and a visible one restarts them', async () => {
    const geolocation = withGeolocation();
    const page = withDocument();
    const sensors = await setup();

    sensors.requestPermissions();
    expect(geolocation.watchPosition).toHaveBeenCalledTimes(1);

    page.hide();
    expect(geolocation.clearWatch).toHaveBeenCalledWith(7);
    target.dispatchEvent(orientationEvent({ alpha: 10, beta: 5, gamma: 1 }));
    expect(sensors.heading.value).toBeNull();

    page.show();
    expect(geolocation.watchPosition).toHaveBeenCalledTimes(2);
    target.dispatchEvent(orientationEvent({ alpha: 10, beta: 5, gamma: 1 }));
    expect(sensors.heading.value).toBe(350);
});

test('orientation bursts are thinned, the last reading always lands', async () => {
    const { ORIENTATION_EMIT_MS } = await import('./useSensors');
    const sensors = await setup();

    sensors.requestPermissions();
    target.dispatchEvent(orientationEvent({ alpha: 10, beta: 5, gamma: 1 }));
    expect(sensors.heading.value).toBe(350);

    target.dispatchEvent(orientationEvent({ alpha: 20, beta: 5, gamma: 1 }));
    target.dispatchEvent(orientationEvent({ alpha: 30, beta: 5, gamma: 1 }));
    expect(sensors.heading.value).toBe(350);

    await vi.advanceTimersByTimeAsync(ORIENTATION_EMIT_MS);
    expect(sensors.heading.value).toBe(330);
});
