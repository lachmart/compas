<script setup>
import { computed } from 'vue';

const props = defineProps({
    // 0..1 — how much of the arrival countdown has passed.
    progress: { type: Number, default: 0 },
});

const RADIUS = 46;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const dashOffset = computed(() => CIRCUMFERENCE * (1 - Math.min(1, Math.max(0, props.progress))));
</script>

<template>
    <!-- Starts at 12 o'clock and fills clockwise. -->
    <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90" aria-hidden="true">
        <circle cx="50" cy="50" :r="RADIUS" fill="none" class="stroke-slate-900/30" stroke-width="5" />
        <circle
            cx="50"
            cy="50"
            :r="RADIUS"
            fill="none"
            class="stroke-white"
            stroke-width="5"
            stroke-linecap="round"
            :stroke-dasharray="CIRCUMFERENCE"
            :stroke-dashoffset="dashOffset"
        />
    </svg>
</template>
