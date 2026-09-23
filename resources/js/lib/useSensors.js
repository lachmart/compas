import { onUnmounted, ref } from 'vue';
import { absoluteOrientation, relativeOrientation } from '@/lib/orientation';
import { needsOrientationPermission, onOrientationGranted, requestOrientationPermission } from '@/lib/orientationPermission';

export function useSensors() {
    const position = ref(null);
    const heading = ref(null);
    const geoError = ref(null);
    const orientationSupported = ref(true);
    const orientation = ref(null);
    // True on iOS while the compass permission is still missing (e.g. a
    // returning player who skipped the join screen): show a tap target
    // that calls enableCompass().
    const needsTap = ref(needsOrientationPermission());
    let hasAbsoluteSource = false;

    let watchId = null;

    const handleOrientation = (event) => {
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

    const listenForOrientation = () => {
        // Chrome also exposes deviceorientationabsolute (true heading);
        // Safari only ever fires plain deviceorientation. Registering the
        // same handler twice is a no-op, so this is safe to repeat.
        window.addEventListener('deviceorientationabsolute', handleOrientation);
        window.addEventListener('deviceorientation', handleOrientation);
        orientationSupported.value = true;
        needsTap.value = false;
    };

    const handleOrientationResult = (result) => {
        if (result === 'granted') {
            listenForOrientation();
        } else {
            orientationSupported.value = false;
            needsTap.value = needsOrientationPermission();
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
            // No prompt needed (Android) or already granted this session.
            listenForOrientation();

            return;
        }

        // Called synchronously, so a click handler invoking
        // requestPermissions() still counts as a user gesture on iOS.
        // Outside a gesture iOS rejects and needsTap asks for a tap.
        handleOrientationResult(await requestOrientationPermission());
    };

    // For a tap target shown while needsTap is true: must be called
    // directly from the click handler (iOS user-gesture requirement).
    const enableCompass = () => requestOrientationPermission().then(handleOrientationResult);

    onUnmounted(() => {
        stopWatchingGrant();
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
        }
        window.removeEventListener('deviceorientation', handleOrientation);
        window.removeEventListener('deviceorientationabsolute', handleOrientation);
    });

    return { position, heading, orientation, geoError, orientationSupported, requestPermissions, needsTap, enableCompass };
}
