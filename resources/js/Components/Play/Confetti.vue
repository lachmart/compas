<script setup>
// A one-shot burst of CSS confetti. Re-mount it (change its :key) to fire
// again. Hidden entirely for people who prefer reduced motion.
const COLORS = ['#0d9488', '#0f766e', '#f97316', '#fbbf24', '#f43f5e', '#38bdf8', '#a3e635'];
const COUNT = 40;

const random = (min, max) => min + Math.random() * (max - min);

const pieces = Array.from({ length: COUNT }, (_, index) => {
    const round = index % 3 === 0;
    const size = random(8, 14);

    return {
        style: {
            left: `${random(0, 100)}%`,
            width: `${size}px`,
            height: `${round ? size : size * 0.45}px`,
            borderRadius: round ? '9999px' : '2px',
            backgroundColor: COLORS[index % COLORS.length],
            animationDelay: `${random(0, 0.35)}s`,
            animationDuration: `${random(1.2, 1.7)}s`,
            '--drift': `${random(-60, 60)}px`,
            '--spin': `${random(360, 900) * (Math.random() < 0.5 ? -1 : 1)}deg`,
        },
    };
});
</script>

<template>
    <div class="pointer-events-none fixed inset-0 z-[1100] hidden overflow-hidden motion-safe:block" aria-hidden="true">
        <span
            v-for="(piece, index) in pieces"
            :key="index"
            class="absolute -top-4 block motion-safe:animate-confetti-fall"
            :style="piece.style"
        ></span>
    </div>
</template>
