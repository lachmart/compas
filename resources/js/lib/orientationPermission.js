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

export function needsOrientationPermission() {
    return hasRequestPermission() && !orientationGranted();
}

// Call synchronously from a tap/click handler (do not await anything before
// it), otherwise iOS rejects the request. Resolves to 'granted', 'denied'
// (also for a rejected request, e.g. outside a gesture) or 'unsupported'.
// Only a grant is remembered, so a later tap can always ask again.
export function requestOrientationPermission() {
    if (!orientationEvent()) return Promise.resolve('unsupported');
    if (orientationGranted()) return Promise.resolve('granted');
    if (inFlight) return inFlight;

    let request;

    try {
        request = Promise.resolve(orientationEvent().requestPermission());
    } catch (error) {
        request = Promise.reject(error);
    }

    inFlight = request
        .then((result) => {
            if (result === 'granted') {
                rememberGrant();

                return 'granted';
            }

            return 'denied';
        })
        .catch(() => 'denied')
        .finally(() => {
            inFlight = null;
        });

    return inFlight;
}

// Notifies when the permission gets granted anywhere in the app (e.g. the
// compass tap target), so every sensor consumer can start listening.
// Returns an unsubscribe function.
export function onOrientationGranted(listener) {
    listeners.add(listener);

    return () => listeners.delete(listener);
}
