import { onUnmounted, ref } from 'vue';
import { unlockAudio } from '@/lib/feedback';
import { absoluteOrientation, relativeOrientation } from '@/lib/orientation';
import {
    ORIENTATION_WATCHDOG_MS,
    needsOrientationPermission,
    onOrientationGranted,
    orientationPermissionGated,
    requestOrientationPermission,
    shouldShowTap,
} from '@/lib/orientationPermission';

// --- Shared sensor streams ---------------------------------------------------
// One geolocation watch and one set of orientation listeners for the whole
// page, however many screens (compass, map, background pings) use them —
// each extra watch would drain the battery for nothing. They pause while the
// page is hidden (screen locked, the phone's camera app open for a photo
// task, another app) and restart when it's back: iOS doesn't reliably resume
// a watch after that, which froze the compass on an old position.

// Orientation drives the UI at most this often; sensors fire at ~60 Hz.
export const ORIENTATION_EMIT_MS = 50;

const position = ref(null);
const geoError = ref(null);
const heading = ref(null);
const orientation = ref(null);

let geoUsers = 0;
let orientationUsers = 0;
let watchId = null;
let listening = false;
let hasAbsoluteSource = false;
let eventSeen = false;
const eventSeenCallbacks = new Set();

let lastEmitAt = 0;
let pendingSample = null;
let trailingTimer = null;

const pageVisible = () => typeof document === 'undefined' || document.visibilityState !== 'hidden';

const emitSample = (sample) => {
    orientation.value = sample.orientation;
    heading.value = sample.heading;
    lastEmitAt = Date.now();
};

// Leading + trailing throttle: the first reading shows at once, bursts are
// thinned to one update per ORIENTATION_EMIT_MS, the last one always lands.
const throttledEmit = (sample) => {
    const wait = ORIENTATION_EMIT_MS - (Date.now() - lastEmitAt);

    if (wait <= 0) {
        clearTimeout(trailingTimer);
        trailingTimer = null;
        emitSample(sample);

        return;
    }

    pendingSample = sample;
    trailingTimer ??= setTimeout(() => {
        trailingTimer = null;
        emitSample(pendingSample);
    }, wait);
};

const handleOrientation = (event) => {
    if (!eventSeen && typeof event.beta === 'number' && typeof event.gamma === 'number') {
        eventSeen = true;
        eventSeenCallbacks.forEach((callback) => callback());
    }

    const absolute = absoluteOrientation(event);

    if (absolute) {
        hasAbsoluteSource = true;
        throttledEmit({ orientation: absolute, heading: (360 - absolute.alpha) % 360 });
    } else if (!hasAbsoluteSource) {
        // Relative-only fallback (no absolute source seen yet), for both the
        // flat arrow and the upright AR aim.
        const relative = relativeOrientation(event);

        if (relative) throttledEmit({ orientation: relative, heading: (360 - relative.alpha) % 360 });
    }
};

const startGeoWatch = () => {
    if (watchId !== null || !pageVisible() || !navigator.geolocation) return;

    watchId = navigator.geolocation.watchPosition(
        (pos) => {
            position.value = { lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy };
            geoError.value = null;
        },
        (error) => {
            geoError.value = error.code === error.PERMISSION_DENIED ? 'denied' : 'unavailable';
        },
        // maximumAge: Infinity hands a newly started watch the last known fix
        // immediately; live updates follow as usual.
        { enableHighAccuracy: true, maximumAge: Infinity },
    );
};

const stopGeoWatch = () => {
    if (watchId === null) return;
    navigator.geolocation.clearWatch(watchId);
    watchId = null;
};

const addOrientationListeners = () => {
    if (listening || !pageVisible()) return;
    // Chrome also exposes deviceorientationabsolute (true heading); Safari
    // only ever fires plain deviceorientation.
    window.addEventListener('deviceorientationabsolute', handleOrientation);
    window.addEventListener('deviceorientation', handleOrientation);
    listening = true;
};

const removeOrientationListeners = () => {
    if (!listening) return;
    window.removeEventListener('deviceorientation', handleOrientation);
    window.removeEventListener('deviceorientationabsolute', handleOrientation);
    listening = false;
};

if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', () => {
        if (!pageVisible()) {
            stopGeoWatch();
            removeOrientationListeners();

            return;
        }

        if (geoUsers > 0) startGeoWatch();
        if (orientationUsers > 0) addOrientationListeners();
    });
}

// --- Per-screen handle ---------------------------------------------------------
// Each screen gets the shared streams plus its own permission UI state
// (tap target, denial), and releases its share when unmounted.

export function useSensors() {
    const orientationSupported = ref(true);
    // True when the compass needs a tap to start (iOS: permission missing,
    // or a remembered grant that didn't unlock events after a reload): show
    // a tap target that calls enableCompass(). Driven by observed events,
    // see startWatchdog().
    const needsTap = ref(false);
    // True once an explicit tap (enableCompass) ended in a denial: the tap
    // target is then withdrawn (a pulsing button that can't work would only
    // frustrate) and the UI shows a static "no compass" state instead.
    const compassDenied = ref(false);
    let watchdogTimer = null;
    let usingGeo = false;
    let usingOrientation = false;

    const onEventSeen = () => {
        needsTap.value = false;
        clearTimeout(watchdogTimer);
        watchdogTimer = null;
    };
    eventSeenCallbacks.add(onEventSeen);

    const startGeolocation = () => {
        if (!navigator.geolocation) {
            geoError.value = 'unsupported';

            return;
        }

        if (!usingGeo) {
            usingGeo = true;
            geoUsers++;
        }

        startGeoWatch();
    };

    // Watchdog: on permission-gated browsers (iOS, recent Chrome) only a real
    // orientation event proves the sensors are unlocked. A grant remembered
    // from before a full reload is not enough on iOS, so if no event arrives
    // in time we ask for a tap (needsTap -> enableCompass).
    const startWatchdog = () => {
        clearTimeout(watchdogTimer);
        watchdogTimer = null;

        if (eventSeen || !orientationPermissionGated()) return;

        const startedAt = Date.now();

        watchdogTimer = setTimeout(() => {
            watchdogTimer = null;
            needsTap.value = shouldShowTap({
                hasRequestPermission: orientationPermissionGated(),
                eventSeen,
                elapsedMs: Date.now() - startedAt,
            });
        }, ORIENTATION_WATCHDOG_MS);
    };

    const listenForOrientation = () => {
        if (!usingOrientation) {
            usingOrientation = true;
            orientationUsers++;
        }

        addOrientationListeners();
        orientationSupported.value = true;
        startWatchdog();
    };

    const handleOrientationResult = (result) => {
        if (result === 'granted') {
            listenForOrientation();
        } else {
            orientationSupported.value = false;
            needsTap.value = orientationPermissionGated();
        }
    };

    // Another screen (or the join screen) may grant the permission while
    // this one is waiting — start listening as soon as that happens.
    const stopWatchingGrant = onOrientationGranted(listenForOrientation);

    const requestPermissions = async () => {
        startGeolocation();

        if (!window.DeviceOrientationEvent) {
            orientationSupported.value = false;

            return;
        }

        if (!needsOrientationPermission()) {
            // No prompt needed (Android) or a grant is remembered from this
            // session. Listen right away; the watchdog catches the iOS case
            // where the grant doesn't carry over to a reloaded page.
            listenForOrientation();

            if (orientationPermissionGated()) {
                // Re-ask quietly: may unlock events without a prompt, or
                // reject outside a gesture (then the watchdog/tap handles it).
                requestOrientationPermission({ force: true });
            }

            return;
        }

        // Called synchronously, so a click handler invoking
        // requestPermissions() still counts as a user gesture on iOS.
        // Outside a gesture iOS rejects and needsTap asks for a tap.
        handleOrientationResult(await requestOrientationPermission());
    };

    // For the tap target shown while needsTap is true: must be called
    // directly from the click handler (iOS user-gesture requirement).
    // Always asks the browser, even if a grant is remembered.
    const enableCompass = () => {
        // Same tap unlocks sound for the game (iOS gesture rule).
        unlockAudio();

        return requestOrientationPermission({ force: true }).then((result) => {
            if (result === 'granted') {
                compassDenied.value = false;
                listenForOrientation();

                return;
            }

            orientationSupported.value = false;
            needsTap.value = false;
            compassDenied.value = true;
        });
    };

    onUnmounted(() => {
        stopWatchingGrant();
        eventSeenCallbacks.delete(onEventSeen);
        clearTimeout(watchdogTimer);

        if (usingGeo && --geoUsers === 0) stopGeoWatch();
        if (usingOrientation && --orientationUsers === 0) removeOrientationListeners();
    });

    return { position, heading, orientation, geoError, orientationSupported, requestPermissions, needsTap, compassDenied, enableCompass };
}
