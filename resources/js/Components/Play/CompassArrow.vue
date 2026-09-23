<script setup>
import { ref, watch } from 'vue';
import { unwrapAngle } from '@/lib/proximity';

const props = defineProps({
    // 0 = the target is straight ahead; clockwise degrees.
    rotation: { type: Number, default: 0 },
    // Greyed-out arrow, e.g. while the direction isn't known yet.
    dimmed: { type: Boolean, default: false },
});

// Unbounded angle so the CSS transition takes the short way round
// (359 -> 1 turns 2 degrees, not 358 backwards).
const angle = ref(props.rotation);

watch(
    () => props.rotation,
    (value) => {
        angle.value = unwrapAngle(angle.value, value);
    },
);
</script>

<template>
    <div class="relative h-full w-full">
        <!-- Dial: translucent disc with four ticks. -->
        <svg viewBox="0 0 100 100" class="absolute inset-0 h-full w-full" aria-hidden="true">
            <circle cx="50" cy="50" r="48" class="fill-white/25 stroke-white/80" stroke-width="2" />
            <circle cx="50" cy="50" r="40" fill="none" class="stroke-white/40" stroke-width="1" />
            <g class="stroke-white" stroke-width="3" stroke-linecap="round">
                <line x1="50" y1="4" x2="50" y2="11" />
                <line x1="96" y1="50" x2="89" y2="50" />
                <line x1="50" y1="96" x2="50" y2="89" />
                <line x1="4" y1="50" x2="11" y2="50" />
            </g>
        </svg>

        <!-- The arrow is drawn pointing UP, so `rotation` (0 = ahead) is used
             as is — no -90deg offset like the old right-pointing glyph. -->
        <svg
            viewBox="0 0 100 100"
            class="absolute inset-0 h-full w-full drop-shadow-[0_6px_6px_rgba(15,23,42,0.45)] motion-safe:transition-transform motion-safe:duration-300 motion-safe:ease-out"
            :class="dimmed ? 'opacity-40' : ''"
            :style="{ transform: `rotate(${angle}deg)` }"
            aria-hidden="true"
        >
            <path
                d="M50 12 L78 80 L50 66 L22 80 Z"
                :class="dimmed ? 'fill-slate-300' : 'fill-white'"
                class="stroke-slate-900"
                stroke-width="5"
                stroke-linejoin="round"
            />
            <path d="M50 12 L50 66 L22 80 Z" class="fill-slate-900/10" />
        </svg>
    </div>
</template>
