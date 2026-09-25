<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { arrowRotationDegrees, bearingDegrees, distanceMeters } from '@/lib/geo';
import { aimTarget, cameraHeading, nextPosture, smoothAngle } from '@/lib/orientation';
import { HEAT_LEVELS, heatGradient, heatLevel } from '@/lib/proximity';
import { isSessionGone, reloadForGoneSession } from '@/lib/playSession';
import { cameraPreference } from '@/lib/cameraPreference';
import { useSensors } from '@/lib/useSensors';
import ArAim from '@/Components/Play/ArAim.vue';
import ArrivalRing from '@/Components/Play/ArrivalRing.vue';
import CameraFeed from '@/Components/Play/CameraFeed.vue';
import CompassArrow from '@/Components/Play/CompassArrow.vue';
import ErrorFlash from '@/Components/Play/ErrorFlash.vue';

const props = defineProps({
    location: { type: Object, required: true },
    gameSlug: { type: String, required: true },
});
const emit = defineEmits(['arrived']);

const { position, heading, orientation, geoError, orientationSupported, requestPermissions, needsTap, compassDenied, enableCompass } = useSensors();
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
// shows the AR aim over the camera picture. nextPosture() applies the
// hysteresis band.
const posture = ref('flat');

// Camera direction, smoothed so the on-screen target doesn't jitter.
const SMOOTHING = 0.2;
const cameraDirection = ref(null);

watch(orientation, (value) => {
    if (!value) return;

    posture.value = nextPosture(posture.value, value.beta, value.gamma);
    cameraDirection.value = posture.value === 'upright'
        ? smoothAngle(cameraDirection.value, cameraHeading(value.alpha, value.beta, value.gamma), SMOOTHING)
        : null;
});

const arMode = computed(() => posture.value === 'upright' && cameraDirection.value !== null && bearing.value !== null);

// A ref (not a computed) so its previous `aligned` can feed back into
// aimTarget() for hysteresis; watch() with explicit sources avoids a
// self-triggering effect.
const aim = ref(null);

watch([arMode, cameraDirection, bearing], () => {
    aim.value = arMode.value ? aimTarget(bearing.value, cameraDirection.value, aim.value?.aligned ?? false) : null;
});

// A refused or missing camera isn't asked for again on every tilt, and a
// player who chose "without camera" on the join screen is never asked.
const cameraUnavailable = ref(cameraPreference() === 'declined');

// Hot/cold background: one layer per heat level, crossfaded by opacity
// (gradients themselves can't be CSS-transitioned).
const heat = computed(() => heatLevel(distance.value));
const heatLayers = Array.from({ length: HEAT_LEVELS + 1 }, (_, level) => heatGradient(level));

// Thermometer segments light up from cool to hot as heat rises.
const thermometerColors = ['bg-cyan-300', 'bg-yellow-300', 'bg-amber-400', 'bg-orange-500', 'bg-red-600'];

// Arrival ring: purely visual progress of the 3 s arrival timer below.
// Started/stopped next to the timer; it never drives the timer itself.
const ARRIVAL_MS = 3000;
const arrivalProgress = ref(null); // null = no countdown running
let progressFrame = null;

const stopArrivalProgress = () => {
    if (progressFrame !== null) cancelAnimationFrame(progressFrame);
    progressFrame = null;
    arrivalProgress.value = null;
};

const startArrivalProgress = () => {
    stopArrivalProgress();
    const startedAt = performance.now();
    arrivalProgress.value = 0;

    const tick = (now) => {
        arrivalProgress.value = Math.min(1, (now - startedAt) / ARRIVAL_MS);
        progressFrame = arrivalProgress.value < 1 ? requestAnimationFrame(tick) : null;
    };

    progressFrame = requestAnimationFrame(tick);
};

let arrivalTimer = null;

const confirmArrival = async () => {
    hasArrivalError.value = false;

    try {
        const response = await window.axios.post(route('play.attempts.arrive', [props.gameSlug, props.location.id]));
        emit('arrived', response.data);
    } catch (error) {
        if (isSessionGone(error)) {
            reloadForGoneSession();

            return;
        }

        console.error('Failed to confirm arrival', error);
        hasArrivalError.value = true;
    } finally {
        arrivalTimer = null;
        stopArrivalProgress();
    }
};

watch(distance, (value) => {
    if (value !== null && value <= props.location.arrival_radius_m) {
        if (!arrivalTimer) {
            arrivalTimer = setTimeout(confirmArrival, ARRIVAL_MS);
            startArrivalProgress();
        }
    } else if (arrivalTimer) {
        clearTimeout(arrivalTimer);
        arrivalTimer = null;
        stopArrivalProgress();
    }
});

watch(
    () => props.location.id,
    () => {
        if (arrivalTimer) {
            clearTimeout(arrivalTimer);
        }
        arrivalTimer = null;
        stopArrivalProgress();
    },
);

onUnmounted(() => {
    if (arrivalTimer) clearTimeout(arrivalTimer);
    if (progressFrame !== null) cancelAnimationFrame(progressFrame);
});
</script>

<template>
    <!-- Location unavailable: whole screen is one retry button. -->
    <button
        v-if="geoError"
        type="button"
        @click="requestPermissions"
        aria-label="Zkusit znovu"
        class="flex h-dvh w-full flex-col items-center justify-center gap-10 bg-gradient-to-b from-rose-100 to-red-200"
    >
        <span class="flex h-44 w-44 items-center justify-center rounded-full bg-white shadow-xl ring-8 ring-red-300/60">
            <svg viewBox="0 0 24 24" class="h-28 w-28 text-red-500" aria-hidden="true">
                <path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5Z" />
                <line x1="3" y1="3" x2="21" y2="21" stroke="white" stroke-width="4" stroke-linecap="round" />
                <line x1="3" y1="3" x2="21" y2="21" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
        </span>
        <span class="flex h-24 w-24 items-center justify-center rounded-full bg-red-500 text-white shadow-lg active:scale-95">
            <svg viewBox="0 0 24 24" class="h-14 w-14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 12a8 8 0 1 1-2.34-5.66" />
                <path d="M20 4v5h-5" />
            </svg>
        </span>
    </button>

    <div v-else class="relative h-dvh w-full overflow-hidden">
        <!-- Heat background: all levels stacked, the current one faded in. -->
        <div
            v-for="(gradient, level) in heatLayers"
            :key="level"
            class="absolute inset-0 motion-safe:transition-opacity motion-safe:duration-1000"
            :class="[gradient, level === heat ? 'opacity-100' : 'opacity-0']"
            aria-hidden="true"
        ></div>

        <!-- Upright: the camera picture (the heat colours stay behind it if
             there's no camera) with the AR aim on top. -->
        <CameraFeed v-if="aim && !cameraUnavailable" class="absolute inset-0" @unavailable="cameraUnavailable = true" />

        <ErrorFlash :show="hasArrivalError" />

        <ArAim v-if="aim" :aim="aim" :distance="distance" :arrival-progress="arrivalProgress" />

        <div v-else class="relative flex h-full flex-col items-center justify-center gap-12 px-4 pb-24 pt-24">
            <!-- Dial with the arrow. -->
            <div class="relative aspect-square w-72 max-w-[80vw]">
                <div v-if="arrivalProgress !== null" class="pointer-events-none absolute -inset-8">
                    <ArrivalRing :progress="arrivalProgress" />
                </div>

                <CompassArrow v-if="hasHeading" :rotation="rotation" :dimmed="bearing === null" />

                <!-- No heading yet. On iOS the compass must be switched on by a tap
                     (withdrawn after a denied tap: the static icon below stays). -->
                <button
                    v-else-if="needsTap && !compassDenied"
                    type="button"
                    @click="enableCompass"
                    aria-label="Zapnout kompas"
                    class="flex h-full w-full items-center justify-center rounded-full bg-white/25 ring-2 ring-white/80 active:scale-95"
                >
                    <span class="flex h-36 w-36 items-center justify-center rounded-full bg-white shadow-xl motion-safe:animate-pulse">
                        <svg viewBox="0 0 24 24" class="h-24 w-24 text-teal-700" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" />
                            <path d="M15.5 8.5 13.4 13.4 10.6 10.6Z" class="fill-orange-500" stroke="none" />
                            <path d="M15.5 8.5 13.4 13.4 8.5 15.5 10.6 10.6Z" />
                        </svg>
                    </span>
                </button>

                <div
                    v-else
                    class="flex h-full w-full items-center justify-center rounded-full bg-white/15 ring-2 ring-white/50"
                    aria-hidden="true"
                >
                    <svg viewBox="0 0 24 24" class="h-24 w-24 text-white/60" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <path d="M15.5 8.5 13.4 13.4 8.5 15.5 10.6 10.6Z" />
                    </svg>
                </div>
            </div>

            <!-- Thermometer: more segments lit = warmer. -->
            <div class="flex flex-col items-center gap-3" aria-hidden="true">
                <div class="flex items-end gap-2 rounded-full bg-slate-900/35 p-2 shadow-lg">
                    <span
                        v-for="(color, index) in thermometerColors"
                        :key="index"
                        class="w-8 rounded-full ring-2 ring-white/70 motion-safe:transition-colors motion-safe:duration-500"
                        :class="[index < heat ? color : 'bg-white/25', ['h-4', 'h-5', 'h-6', 'h-7', 'h-8'][index]]"
                    ></span>
                </div>
                <p
                    v-if="distance !== null"
                    class="rounded-full bg-slate-900/60 px-3 py-0.5 text-lg font-bold tabular-nums text-white"
                >{{ Math.round(distance) }} m</p>
            </div>
        </div>
    </div>
</template>
