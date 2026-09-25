<script setup>
import { computed, watch } from 'vue';
import ArrivalRing from '@/Components/Play/ArrivalRing.vue';

// AR overlay for the upright phone: a fixed reticle in the middle and the
// target drawn where it really is (see aimTarget() in orientation.js).
const props = defineProps({
    // { offset, direction, aligned } from aimTarget().
    aim: { type: Object, required: true },
    distance: { type: Number, default: null },
    // 0..1 while the arrival countdown runs, else null.
    arrivalProgress: { type: Number, default: null },
});

// Only the middle 80 % of the width, so the marker never leaves the screen.
const markerLeft = computed(() => `${50 + props.aim.offset * 40}%`);

const distanceLabel = computed(() => (props.distance === null ? null : `${Math.round(props.distance)} m`));

// One short buzz the moment the player aims at the target; not repeated
// while they stay on it. iOS ignores web vibration — the reticle still
// turns green.
watch(
    () => props.aim.aligned,
    (aligned, was) => {
        if (aligned && !was) navigator.vibrate?.(120);
    },
    { immediate: true },
);
</script>

<template>
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
        <!-- Horizon guide through the reticle. -->
        <div class="absolute inset-x-0 top-1/2 h-px bg-white/40"></div>

        <!-- Target marker at its real direction. Only the target itself is
             centred on the horizon; the distance hangs below it. -->
        <div
            v-if="aim.offset !== null"
            class="absolute top-1/2 -translate-x-1/2 -translate-y-1/2 motion-safe:transition-[left] motion-safe:duration-100 motion-safe:ease-linear"
            :style="{ left: markerLeft }"
        >
            <svg
                viewBox="0 0 48 48"
                class="block drop-shadow-[0_2px_6px_rgba(15,23,42,0.6)] motion-safe:transition-transform"
                :class="aim.aligned ? 'h-24 w-24 scale-110' : 'h-20 w-20'"
            >
                <circle cx="24" cy="24" r="21" class="fill-white" />
                <circle cx="24" cy="24" r="15" class="fill-orange-500" />
                <circle cx="24" cy="24" r="9" class="fill-white" />
                <circle cx="24" cy="24" r="4" class="fill-orange-500" />
            </svg>
            <span
                v-if="distanceLabel"
                class="absolute left-1/2 top-full mt-8 -translate-x-1/2 whitespace-nowrap rounded-full bg-slate-900/70 px-3 py-0.5 text-lg font-bold tabular-nums text-white"
            >{{ distanceLabel }}</span>
        </div>

        <!-- Reticle: where the camera points. Green when aimed. -->
        <div class="absolute left-1/2 top-1/2 h-36 w-36 -translate-x-1/2 -translate-y-1/2">
            <div v-if="arrivalProgress !== null" class="absolute -inset-6">
                <ArrivalRing :progress="arrivalProgress" />
            </div>
            <svg viewBox="0 0 100 100" class="h-full w-full drop-shadow-[0_1px_3px_rgba(15,23,42,0.7)]" fill="none" stroke-linecap="round">
                <circle cx="50" cy="50" r="44" stroke-width="4" :class="aim.aligned ? 'stroke-green-400' : 'stroke-white'" />
                <g stroke-width="5" :class="aim.aligned ? 'stroke-green-400' : 'stroke-white'">
                    <line x1="50" y1="0" x2="50" y2="16" />
                    <line x1="50" y1="84" x2="50" y2="100" />
                    <line x1="0" y1="50" x2="16" y2="50" />
                    <line x1="84" y1="50" x2="100" y2="50" />
                </g>
            </svg>
        </div>

        <!-- Target outside the picture: which way to turn. -->
        <div
            v-if="aim.direction === 'left' || aim.direction === 'right'"
            class="absolute top-1/2 flex -translate-y-1/2 items-center"
            :class="aim.direction === 'left' ? 'left-3' : 'right-3'"
        >
            <span class="flex h-24 w-24 items-center justify-center rounded-full bg-orange-500 text-white shadow-xl ring-4 ring-white motion-safe:animate-pulse">
                <svg viewBox="0 0 24 24" class="h-16 w-16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" :class="{ 'rotate-180': aim.direction === 'left' }">
                    <path d="M9 5l7 7-7 7" />
                </svg>
            </span>
        </div>

        <!-- Target behind the player: turn around. -->
        <div v-else-if="aim.direction === 'behind'" class="absolute inset-x-0 top-[62%] flex justify-center">
            <span class="flex h-24 w-24 items-center justify-center rounded-full bg-orange-500 text-white shadow-xl ring-4 ring-white motion-safe:animate-pulse">
                <svg viewBox="0 0 24 24" class="h-16 w-16" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 14 4 9l5-5" />
                    <path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11" />
                </svg>
            </span>
        </div>

        <!-- Distance stays readable while the target is out of the picture. -->
        <div v-if="aim.offset === null && distanceLabel" class="absolute inset-x-0 bottom-[max(6.5rem,calc(env(safe-area-inset-bottom)+5rem))] flex justify-center">
            <span class="rounded-full bg-slate-900/70 px-4 py-1 text-xl font-bold tabular-nums text-white">{{ distanceLabel }}</span>
        </div>
    </div>
</template>
