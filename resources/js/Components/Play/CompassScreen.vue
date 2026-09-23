<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { arrowRotationDegrees, bearingDegrees, distanceMeters, proximityColor } from '@/lib/geo';
import { cameraHeading, nextPosture, turnGuidance } from '@/lib/orientation';
import { useSensors } from '@/lib/useSensors';
import ErrorFlash from '@/Components/Play/ErrorFlash.vue';
import TurnGuide from '@/Components/Play/TurnGuide.vue';

const props = defineProps({
    location: { type: Object, required: true },
    gameSlug: { type: String, required: true },
});
const emit = defineEmits(['arrived']);

const { position, heading, orientation, geoError, orientationSupported, requestPermissions } = useSensors();
requestPermissions();

const hasArrivalError = ref(false);

const distance = computed(() => {
    if (!position.value) return null;

    return distanceMeters(position.value.lat, position.value.lng, props.location.lat, props.location.lng);
});

const hasHeading = computed(() => orientationSupported.value && heading.value !== null);

const bearing = computed(() => {
    if (!position.value) return null;

    return bearingDegrees(position.value.lat, position.value.lng, props.location.lat, props.location.lng);
});

const rotation = computed(() => {
    if (bearing.value === null || !hasHeading.value) return 0;

    return arrowRotationDegrees(bearing.value, heading.value);
});

// 'flat' shows the classic arrow; 'upright' (phone held like a camera)
// shows turn guidance. nextPosture() applies the hysteresis band.
const posture = ref('flat');

watch(orientation, (value) => {
    if (value) posture.value = nextPosture(posture.value, value.beta, value.gamma);
});

// A ref (not a computed) so its own previous value can feed back into
// turnGuidance() for on-course hysteresis (see orientation.js). watch()
// with an explicit source list avoids a self-triggering effect.
const guidance = ref(null);

watch([posture, orientation, bearing], () => {
    if (posture.value !== 'upright' || !orientation.value || bearing.value === null) {
        guidance.value = null;
        return;
    }

    const { alpha, beta, gamma } = orientation.value;

    guidance.value = turnGuidance(bearing.value, cameraHeading(alpha, beta, gamma), guidance.value);
});

const backgroundColor = computed(() => {
    if (distance.value === null) return 'bg-gray-200';

    return {
        red: 'bg-red-400',
        orange: 'bg-orange-400',
        yellow: 'bg-yellow-300',
        blue: 'bg-blue-300',
    }[proximityColor(distance.value)];
});

let arrivalTimer = null;

const confirmArrival = async () => {
    hasArrivalError.value = false;

    try {
        const response = await window.axios.post(route('play.attempts.arrive', [props.gameSlug, props.location.id]));
        emit('arrived', response.data);
    } catch (error) {
        console.error('Failed to confirm arrival', error);
        hasArrivalError.value = true;
    } finally {
        arrivalTimer = null;
    }
};

watch(distance, (value) => {
    if (value !== null && value <= props.location.arrival_radius_m) {
        if (!arrivalTimer) {
            arrivalTimer = setTimeout(confirmArrival, 3000);
        }
    } else if (arrivalTimer) {
        clearTimeout(arrivalTimer);
        arrivalTimer = null;
    }
});

watch(
    () => props.location.id,
    () => {
        if (arrivalTimer) {
            clearTimeout(arrivalTimer);
        }
        arrivalTimer = null;
    },
);

onUnmounted(() => {
    if (arrivalTimer) clearTimeout(arrivalTimer);
});
</script>

<template>
    <button
        v-if="geoError"
        type="button"
        @click="requestPermissions"
        class="flex h-screen w-full flex-col items-center justify-center gap-6 bg-red-100"
    >
        <svg viewBox="0 0 24 24" class="h-24 w-24 text-red-500" fill="currentColor">
            <path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5Z" />
            <line x1="3" y1="3" x2="21" y2="21" stroke="currentColor" stroke-width="2" />
        </svg>
        <span class="text-4xl text-red-500">↻</span>
    </button>

    <div v-else class="relative flex h-screen flex-col items-center justify-center gap-8 transition-colors duration-500" :class="backgroundColor">
        <ErrorFlash :show="hasArrivalError" />
        <TurnGuide v-if="guidance" :guidance="guidance" />
        <!-- ➤ (U+27A4) points right, not up, so rotation needs an extra -90deg
             to line the glyph up with `rotation` (0 = pointing ahead). -->
        <div
            v-else-if="hasHeading"
            class="text-6xl"
            :style="{ transform: `rotate(${rotation - 90}deg)` }"
        >➤</div>
        <div v-else class="text-6xl text-gray-400 opacity-30">◎</div>
        <p v-if="distance !== null" class="text-xl font-semibold text-gray-700">{{ Math.round(distance) }} m</p>
    </div>
</template>
