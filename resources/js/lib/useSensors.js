import { onUnmounted, ref } from 'vue';
import { absoluteOrientation, relativeOrientation } from '@/lib/orientation';
import {
    ORIENTATION_WATCHDOG_MS,
    needsOrientationPermission,
    onOrientationGranted,
    orientationPermissionGated,
    requestOrientationPermission,
    shouldShowTap,
} from '@/lib/orientationPermission';

export function useSensors() {
    const position = ref(null);
    const heading = ref(null);
    const geoError = ref(null);
    const orientationSupported = ref(true);
    const orientation = ref(null);
    // True when the compass needs a tap to start (iOS: permission missing,
    // or a remembered grant that didn't unlock events after a reload):
    // show a tap target that calls enableCompass(). Driven by observed
    // events, see startWatchdog().
    const needsTap = ref(false);
    let eventSeen = false;
    let watchdogTimer = null;
    let hasAbsoluteSource = false;

    let watchId = null;

    const handleOrientation = (event) => {
        if (!eventSeen && typeof event.beta === 'number' && typeof event.gamma === 'number') {
            eventSeen = true;
            needsTap.value = false;
            clearTimeout(watchdogTimer);
            watchdogTimer = null;
        }

        const absolute = absoluteOrientation(event);

        if (absolute) {
            hasAbsoluteSource = true;
            orientation.value = absolute;
            heading.value = (360 - absolute.alpha) % 360;
        } else if (!hasAbsoluteSource) {
            // Relative-only fallback (no absolute source seen yet), for both
            // the flat arrow and the upright turn guidance.
            const relative = relativeOrientation(event);

            if (relative) {
                orientation.value = relative;
                heading.value = (360 - relative.alpha) % 360;
            }
        }
    };

    const startGeolocation = () => {
        if (!navigator.geolocation) {
            geoError.value = 'unsupported';

            return;
        }

        watchId = navigator.geolocation.watchPosition(
            (pos) => {
                position.value = {
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude,
                    accuracy: pos.coords.accuracy,
                };
                geoError.value = null;
            },
            (error) => {
                geoError.value = error.code === error.PERMISSION_DENIED ? 'denied' : 'unavailable';
            },
            // maximumAge: Infinity hands a newly mounted screen the last known
            // fix immediately. With a short maximumAge, a watch started while
            // the device stands still (e.g. switching compass -> map) gets no
            // position until the player moves, because the platform only
            // reports changes. Live updates still follow as usual.
            { enableHighAccuracy: true, maximumAge: Infinity },
        );
    };

    // Watchdog: on permission-gated browsers (iOS, recent Chrome) only a
    // real orientation event proves the sensors are unlocked. A grant
    // remembered from before a full reload is not enough on iOS, so if no
    // event arrives in time we ask for a tap (needsTap -> enableCompass).
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
        // Chrome also exposes deviceorientationabsolute (true heading);
        // Safari only ever fires plain deviceorientation. Registering the
        // same handler twice is a no-op, so this is safe to repeat.
        window.addEventListener('deviceorientationabsolute', handleOrientation);
        window.addEventListener('deviceorientation', handleOrientation);
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

    // Another screen (or the join button) may grant the permission while
    // this instance is waiting — start listening as soon as that happens.
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
    const enableCompass = () => requestOrientationPermission({ force: true }).then(handleOrientationResult);

    onUnmounted(() => {
        stopWatchingGrant();
        clearTimeout(watchdogTimer);
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
        }
        window.removeEventListener('deviceorientation', handleOrientation);
        window.removeEventListener('deviceorientationabsolute', handleOrientation);
    });

    return { position, heading, orientation, geoError, orientationSupported, requestPermissions, needsTap, enableCompass };
}
