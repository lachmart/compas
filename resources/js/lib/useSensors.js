import { onUnmounted, ref } from 'vue';
import { absoluteOrientation, relativeOrientation } from '@/lib/orientation';

export function useSensors() {
    const position = ref(null);
    const heading = ref(null);
    const geoError = ref(null);
    const orientationSupported = ref(true);
    const orientation = ref(null);
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

    const requestPermissions = async () => {
        startGeolocation();

        const OrientationEvent = window.DeviceOrientationEvent;

        if (OrientationEvent && typeof OrientationEvent.requestPermission === 'function') {
            try {
                const result = await OrientationEvent.requestPermission();

                if (result === 'granted') {
                    // Chrome now also exposes requestPermission (unlike Safari,
                    // which only ever fires plain deviceorientation), so we
                    // register both here too to get absolute heading there.
                    window.addEventListener('deviceorientationabsolute', handleOrientation);
                    window.addEventListener('deviceorientation', handleOrientation);
                } else {
                    orientationSupported.value = false;
                }
            } catch {
                orientationSupported.value = false;
            }
        } else if (OrientationEvent) {
            window.addEventListener('deviceorientationabsolute', handleOrientation);
            window.addEventListener('deviceorientation', handleOrientation);
        } else {
            orientationSupported.value = false;
        }
    };

    onUnmounted(() => {
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
        }
        window.removeEventListener('deviceorientation', handleOrientation);
        window.removeEventListener('deviceorientationabsolute', handleOrientation);
    });

    return { position, heading, orientation, geoError, orientationSupported, requestPermissions };
}
