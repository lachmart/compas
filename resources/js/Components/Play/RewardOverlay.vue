<script setup>
import { onMounted, onUnmounted } from 'vue';
import Confetti from '@/Components/Play/Confetti.vue';
import { celebrate } from '@/lib/feedback';

const emit = defineEmits(['done']);

// Matches the star-fly animation length (tailwind.config.js).
const DURATION_MS = 1600;

let timer = null;

onMounted(() => {
    celebrate();
    timer = setTimeout(() => emit('done'), DURATION_MS);
});

onUnmounted(() => clearTimeout(timer));
</script>

<template>
    <div class="relative flex h-dvh w-full items-center justify-center overflow-hidden bg-gradient-to-b from-amber-200 via-yellow-300 to-orange-400">
        <!-- Soft glow behind the star. -->
        <div class="absolute h-80 w-80 rounded-full bg-white/50 blur-3xl" aria-hidden="true"></div>

        <Confetti />

        <!-- The star pops up, then flies to the stars row in the HUD. -->
        <svg viewBox="0 0 24 24" class="relative h-48 w-48 drop-shadow-xl motion-safe:animate-star-fly" aria-hidden="true">
            <path
                d="M12 2.5l2.9 6 6.6.8-4.9 4.5 1.3 6.5L12 17l-5.9 3.3 1.3-6.5-4.9-4.5 6.6-.8z"
                class="fill-amber-400 stroke-amber-600"
                stroke-width="1"
                stroke-linejoin="round"
            />
            <path d="M9.2 9.6c.8-1.2 1.6-2.2 2.3-3.4" class="stroke-white/80" stroke-width="1" stroke-linecap="round" fill="none" />
        </svg>
    </div>
</template>
