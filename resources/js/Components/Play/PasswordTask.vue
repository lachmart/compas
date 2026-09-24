<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import ErrorFlash from '@/Components/Play/ErrorFlash.vue';
import { playSuccess, playWrong, unlockAudio } from '@/lib/feedback';
import { isSessionGone, reloadForGoneSession } from '@/lib/playSession';

const props = defineProps({
    gameSlug: { type: String, required: true },
    locationId: { type: Number, required: true },
});
const emit = defineEmits(['completed']);

// How long the green tick stays up before moving on, and how long a wrong
// answer stays red.
const CORRECT_MS = 900;
const WRONG_MS = 1600;

const answer = ref('');
const input = ref(null);
const submitting = ref(false);
const hasError = ref(false);
const correct = ref(false);
const wrong = ref(false);

let wrongTimer = null;
let completeTimer = null;

onMounted(() => nextTick(() => input.value?.focus()));

onUnmounted(() => {
    clearTimeout(wrongTimer);
    clearTimeout(completeTimer);
});

const submit = async () => {
    // Synchronously inside the tap (iOS gesture rule), before any await.
    unlockAudio();

    if (submitting.value || correct.value || answer.value.trim() === '') return;

    submitting.value = true;
    hasError.value = false;

    try {
        const response = await window.axios.post(
            route('play.attempts.password', [props.gameSlug, props.locationId]),
            { answer: answer.value },
        );

        if (response.data.correct) {
            correct.value = true;
            // Short sound only: the reward overlay that follows celebrates.
            playSuccess();
            completeTimer = setTimeout(() => emit('completed', response.data.player), CORRECT_MS);
        } else {
            clearTimeout(wrongTimer);
            wrong.value = true;
            playWrong();
            wrongTimer = setTimeout(() => (wrong.value = false), WRONG_MS);
            input.value?.select();
        }
    } catch (error) {
        if (isSessionGone(error)) {
            reloadForGoneSession();

            return;
        }

        console.error('Failed to submit password', error);
        hasError.value = true;
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <div class="flex h-dvh w-full flex-col items-center overflow-y-auto bg-gradient-to-b from-teal-400 to-teal-700 px-4 pb-8 pt-28">
        <ErrorFlash :show="hasError" />

        <form class="my-auto flex w-full max-w-sm flex-col items-center gap-5" @submit.prevent="submit">
            <span class="flex h-24 w-24 items-center justify-center rounded-full bg-white text-teal-700 shadow-xl" aria-hidden="true">
                <svg viewBox="0 0 24 24" class="h-14 w-14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                </svg>
            </span>

            <label for="password-answer" class="text-center text-2xl font-bold text-white drop-shadow">Zadej heslo</label>

            <!-- 16px+ text so iOS doesn't zoom in on focus. -->
            <input
                id="password-answer"
                ref="input"
                v-model="answer"
                type="text"
                maxlength="200"
                autocomplete="off"
                autocapitalize="off"
                autocorrect="off"
                spellcheck="false"
                enterkeyhint="done"
                :disabled="submitting || correct"
                class="w-full rounded-2xl border-0 bg-white px-5 py-4 text-center text-2xl font-semibold text-slate-900 shadow-xl ring-4 transition focus:outline-none"
                :class="correct ? 'ring-green-400' : wrong ? 'ring-red-400 motion-safe:animate-shake' : 'ring-white/60 focus:ring-orange-300'"
                @input="wrong = false"
            />

            <p class="h-6 text-center text-lg font-semibold text-white drop-shadow" aria-live="polite">
                <template v-if="correct">Správně!</template>
                <template v-else-if="wrong">Špatné heslo, zkus to znovu</template>
            </p>

            <button
                type="submit"
                :disabled="submitting || correct"
                :aria-label="'Potvrdit'"
                class="flex h-20 w-20 items-center justify-center rounded-full text-white shadow-xl ring-4 ring-white transition active:scale-95 disabled:opacity-70"
                :class="correct ? 'bg-green-500' : 'bg-orange-500'"
            >
                <svg viewBox="0 0 24 24" class="h-12 w-12" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 12.5 10 17.5 19.5 7" />
                </svg>
            </button>
        </form>
    </div>
</template>
