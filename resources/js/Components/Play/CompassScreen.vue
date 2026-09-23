<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { arrowRotationDegrees, bearingDegrees, distanceMeters, proximityColor } from '@/lib/geo';
import { useSensors } from '@/lib/useSensors';
import ErrorFlash from '@/Components/Play/ErrorFlash.vue';

const props = defineProps({
    location: { type: Object, required: true },
    gameSlug: { type: String, required: true },
});
const emit = defineEmits(['arrived']);

const { position, heading, geoError, orientationSupported, requestPermissions } = useSensors();
requestPermissions();

const hasArrivalError = ref(false);

const distance = computed(() => {
    if (!position.value) return null;

    return distanceMeters(position.value.lat, position.value.lng, props.location.lat, props.location.lng);
});

const hasHeading = computed(() => orientationSupported.value && heading.value !== null);

const rotation = computed(() => {
    if (!position.value || !hasHeading.value) return 0;

    const bearing = bearingDegrees(position.value.lat, position.value.lng, props.location.lat, props.location.lng);

    return arrowRotationDegrees(bearing, heading.value);
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
let pingTimer = null;

const HEARTBEAT_MS = 30000;
let lastPing = null; // { lat, lng, at }

const sendPingIfMoved = () => {
    if (!position.value) return;

    const moved = !lastPing
        || distanceMeters(lastPing.lat, lastPing.lng, position.value.lat, position.value.lng) >= 5;
    const heartbeatDue = !lastPing || Date.now() - lastPing.at >= HEARTBEAT_MS;

    if (!moved && !heartbeatDue) return;

    lastPing = { lat: position.value.lat, lng: position.value.lng, at: Date.now() };
    window.axios
        .post(route('play.pings.store', props.gameSlug), {
            lat: position.value.lat,
            lng: position.value.lng,
            heading: heading.value,
        })
        .catch(() => {});
};

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
    sendPingIfMoved();

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

pingTimer = setInterval(sendPingIfMoved, 5000);

onUnmounted(() => {
    if (arrivalTimer) clearTimeout(arrivalTimer);
    if (pingTimer) clearInterval(pingTimer);
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
        <div
            v-if="hasHeading"
            class="text-6xl"
            :style="{ transform: `rotate(${rotation}deg)` }"
        >➤</div>
        <div v-else class="text-6xl text-gray-400 opacity-30">◎</div>
        <p v-if="distance !== null" class="text-xl font-semibold text-gray-700">{{ Math.round(distance) }} m</p>
    </div>
</template>
