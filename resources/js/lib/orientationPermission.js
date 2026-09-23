// Shared, session-wide state of the compass (DeviceOrientation) permission.
//
// iOS Safari only shows the permission prompt when
// DeviceOrientationEvent.requestPermission() is called synchronously inside a
// user gesture (a click handler). The join screen's play button does that,
// and a later Inertia navigation to the game must not ask again — so the
// grant is kept in memory and in sessionStorage (for reloads within the tab).
// Android/desktop browsers without requestPermission need no prompt at all.

const STORAGE_KEY = 'kompas.orientation';

let grantedInMemory = false;
let inFlight = null;
const listeners = new Set();

const orientationEvent = () => globalThis.DeviceOrientationEvent;

const hasRequestPermission = () => typeof orientationEvent()?.requestPermission === 'function';

const storedGrant = () => {
    try {
        return globalThis.sessionStorage?.getItem(STORAGE_KEY) === 'granted';
    } catch {
        return false;
    }
};

const rememberGrant = () => {
    grantedInMemory = true;

    try {
        globalThis.sessionStorage?.setItem(STORAGE_KEY, 'granted');
    } catch {
        // Private mode / blocked storage: the in-memory flag still covers
        // this page load.
    }

    listeners.forEach((listener) => listener());
};

export function orientationGranted() {
    if (!orientationEvent()) return false;
    if (!hasRequestPermission()) return true;

    return grantedInMemory || storedGrant();
}

// "No grant remembered yet": the browser has a permission API and we have
// no record of a grant. This is NOT "events are flowing" — after a full
// reload iOS may need a fresh requestPermission() despite a remembered
// grant. The UI signal for that is useSensors' needsTap (event watchdog).
export function needsOrientationPermission() {
    return hasRequestPermission() && !orientationGranted();
}

// Call synchronously from a tap/click handler (do not await anything before
// it), otherwise iOS rejects the request. Resolves to 'granted', 'denied'
// (also for a rejected request, e.g. outside a gesture) or 'unsupported'.
// Only a grant is remembered, so a later tap can always ask again.
// { force: true } asks the browser even when a grant is remembered (needed
// to unlock events in a freshly loaded document on iOS); a failed forced
// request does not forget the remembered grant, and never joins a pending
// request (that one may not have been started inside a gesture).
export function requestOrientationPermission({ force = false } = {}) {
    if (!orientationEvent()) return Promise.resolve('unsupported');
    if (!hasRequestPermission()) return Promise.resolve('granted');
    if (!force && orientationGranted()) return Promise.resolve('granted');
    // Join a pending request only when not forced: a forced call comes from
    // a tap, and a pending request may have been started outside a gesture
    // (then iOS rejects it), so the tap must issue its own request.
    if (inFlight && !force) return inFlight;

    let request;

    try {
        request = Promise.resolve(orientationEvent().requestPermission());
    } catch (error) {
        request = Promise.reject(error);
    }

    const pending = request
        .then((result) => {
            if (result === 'granted') {
                rememberGrant();

                return 'granted';
            }

            return 'denied';
        })
        .catch(() => 'denied')
        .finally(() => {
            // A newer (forced) request may have replaced this one.
            if (inFlight === pending) inFlight = null;
        });

    inFlight = pending;

    return pending;
}

export const ORIENTATION_WATCHDOG_MS = 1500;

// Whether to show the "tap to enable the compass" target: only where the
// browser gates orientation behind a permission, and only when no real
// orientation event has arrived within the watchdog window.
export function shouldShowTap({ hasRequestPermission: gated, eventSeen, elapsedMs, timeoutMs = ORIENTATION_WATCHDOG_MS }) {
    return Boolean(gated) && !eventSeen && elapsedMs >= timeoutMs;
}

export function orientationPermissionGated() {
    return hasRequestPermission();
}

// Notifies when the permission gets granted anywhere in the app (e.g. the
// compass tap target), so every sensor consumer can start listening.
// Returns an unsubscribe function.
export function onOrientationGranted(listener) {
    listeners.add(listener);

    return () => listeners.delete(listener);
}
