<script setup>
import { onUnmounted, ref } from 'vue';
import ErrorFlash from '@/Components/Play/ErrorFlash.vue';
import { celebrate, playWrong } from '@/lib/feedback';

const props = defineProps({
    images: { type: Array, required: true },
    gameSlug: { type: String, required: true },
    locationId: { type: Number, required: true },
});
const emit = defineEmits(['completed']);

// How long the green tick stays up before moving on, and how long a wrong
// tile stays red.
const CORRECT_MS = 900;
const WRONG_MS = 600;

const submitting = ref(false);
const hasError = ref(false);
const pressed = ref(null); // tile waiting for the server
const correct = ref(null); // tile confirmed right (no more taps)
const wrong = ref(null); // tile just rejected (shakes, red)

let wrongTimer = null;
let completeTimer = null;

const showWrong = (index) => {
    clearTimeout(wrongTimer);
    wrong.value = index;
    playWrong();
    wrongTimer = setTimeout(() => {
        wrong.value = null;
    }, WRONG_MS);
};

const pick = async (index) => {
    if (submitting.value || correct.value !== null) return;

    submitting.value = true;
    hasError.value = false;
    pressed.value = index;
    wrong.value = null;

    try {
        const response = await window.axios.post(
            route('play.attempts.answer', [props.gameSlug, props.locationId]),
            { selected_index: index },
        );

        if (response.data.correct) {
            correct.value = index;
            celebrate();
            completeTimer = setTimeout(() => emit('completed', response.data.player), CORRECT_MS);
        } else {
            showWrong(index);
        }
    } catch (error) {
        console.error('Failed to submit answer', error);
        hasError.value = true;
    } finally {
        submitting.value = false;
        pressed.value = null;
    }
};

onUnmounted(() => {
    clearTimeout(wrongTimer);
    clearTimeout(completeTimer);
});

const tileClass = (index) => {
    if (correct.value === index) return 'ring-8 ring-green-500 scale-105';
    if (wrong.value === index) return 'ring-8 ring-red-500 motion-safe:animate-shake';
    if (pressed.value === index) return 'ring-4 ring-white scale-90';
    if (submitting.value || correct.value !== null) return 'ring-4 ring-white opacity-60';

    return 'ring-4 ring-white active:scale-90';
};
</script>

<template>
    <div class="flex h-dvh w-full flex-col items-center overflow-y-auto bg-gradient-to-b from-teal-400 to-teal-700 px-4 pb-8 pt-28">
        <ErrorFlash :show="hasError" />

        <div class="my-auto grid w-full max-w-md grid-cols-2 gap-4">
            <button
                v-for="(image, index) in images"
                :key="index"
                type="button"
                :disabled="submitting || correct !== null"
                @click="pick(index)"
                class="relative aspect-square overflow-hidden rounded-3xl bg-white p-1.5 shadow-xl motion-safe:transition motion-safe:duration-150"
                :class="tileClass(index)"
            >
                <img :src="image" alt="" class="h-full w-full rounded-[1.25rem] object-cover" draggable="false" />

                <span
                    v-if="correct === index"
                    class="absolute inset-0 flex items-center justify-center bg-green-500/25"
                    aria-hidden="true"
                >
                    <span class="flex h-24 w-24 items-center justify-center rounded-full bg-green-500 text-white shadow-xl ring-4 ring-white motion-safe:animate-pop">
                        <svg viewBox="0 0 24 24" class="h-16 w-16" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12.5 10 17.5 19.5 7" />
                        </svg>
                    </span>
                </span>
            </button>
        </div>
    </div>
</template>
