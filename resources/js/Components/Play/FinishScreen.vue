<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import Confetti from '@/Components/Play/Confetti.vue';
import { avatarEmoji } from '@/lib/avatars';
import { celebrate } from '@/lib/feedback';

const props = defineProps({
    avatar: { type: String, default: null },
    // Number of stations in the game: one star each.
    stars: { type: Number, default: 0 },
});

const TROPHY = '\u{1F3C6}';
const CONFETTI_EVERY_MS = 3500;

const emoji = computed(() => avatarEmoji(props.avatar));
const small = computed(() => props.stars > 8);

// Bumping the key re-mounts Confetti, which fires a fresh burst.
const burst = ref(0);
let interval = null;

onMounted(() => {
    celebrate();
    interval = setInterval(() => {
        burst.value += 1;
    }, CONFETTI_EVERY_MS);
});

onUnmounted(() => clearInterval(interval));
</script>

<template>
    <div
        class="relative flex h-dvh w-full flex-col items-center justify-center gap-8 overflow-hidden bg-gradient-to-b from-amber-200 via-yellow-300 to-orange-400 px-4"
    >
        <div class="absolute top-1/4 h-80 w-80 rounded-full bg-white/50 blur-3xl" aria-hidden="true"></div>

        <Confetti :key="burst" />

        <div class="relative text-[9rem] leading-none drop-shadow-xl motion-safe:animate-bounce" aria-hidden="true">{{ TROPHY }}</div>

        <div
            class="relative flex h-28 w-28 items-center justify-center rounded-full bg-white text-7xl shadow-xl ring-8 ring-white/60 motion-safe:animate-pop"
            aria-hidden="true"
        >
            {{ emoji }}
        </div>

        <div
            v-if="stars > 0"
            class="relative flex max-w-sm flex-wrap items-center justify-center rounded-3xl bg-white/90 px-4 py-3 shadow-lg"
            :class="small ? 'gap-1.5' : 'gap-2'"
            aria-hidden="true"
        >
            <svg
                v-for="index in stars"
                :key="index"
                viewBox="0 0 24 24"
                class="motion-safe:animate-pop"
                :class="small ? 'h-7 w-7' : 'h-10 w-10'"
                :style="{ animationDelay: `${0.3 + index * 0.12}s` }"
            >
                <path
                    d="M12 2.5l2.9 6 6.6.8-4.9 4.5 1.3 6.5L12 17l-5.9 3.3 1.3-6.5-4.9-4.5 6.6-.8z"
                    class="fill-amber-400 stroke-amber-600"
                    stroke-width="1.5"
                    stroke-linejoin="round"
                />
            </svg>
        </div>
    </div>
</template>
