import { ref } from 'vue';
import { cameraPreference, requestCamera, setCameraPreference } from '@/lib/cameraPreference';

// One reactive copy of the camera choice for the whole page, so turning the
// camera on or off in the help takes effect on the compass right away.
const preference = ref(cameraPreference());

export function useCameraPreference() {
    return {
        preference,
        async enableCamera() {
            const result = await requestCamera();
            preference.value = cameraPreference();

            return result;
        },
        disableCamera() {
            setCameraPreference('declined');
            preference.value = 'declined';
        },
        setPreference(value) {
            setCameraPreference(value);
            preference.value = value;
        },
    };
}
