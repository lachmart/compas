import { onUnmounted, ref } from 'vue';

export function useSensors() {
    const position = ref(null);
    const heading = ref(null);
    const geoError = ref(null);
    const orientationSupported = ref(true);

    let watchId = null;

    const handleOrientation = (event) => {
        if (typeof event.webkitCompassHeading === 'number') {
            heading.value = event.webkitCompassHeading;
        } else if (typeof event.alpha === 'number') {
            heading.value = (360 - event.alpha) % 360;
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
            { enableHighAccuracy: true, maximumAge: 1000 },
        );
    };

    const requestPermissions = async () => {
        startGeolocation();

        const OrientationEvent = window.DeviceOrientationEvent;

        if (OrientationEvent && typeof OrientationEvent.requestPermission === 'function') {
            try {
                const result = await OrientationEvent.requestPermission();

                if (result === 'granted') {
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

    return { position, heading, geoError, orientationSupported, requestPermissions };
}
