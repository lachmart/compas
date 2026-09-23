<script setup>
import { onUnmounted, ref } from 'vue';
import ErrorFlash from '@/Components/Play/ErrorFlash.vue';
import { playSuccess, unlockAudio } from '@/lib/feedback';

const props = defineProps({
    referenceImage: { type: String, required: true },
    taskType: { type: String, required: true },
    gameSlug: { type: String, required: true },
    locationId: { type: Number, required: true },
});
const emit = defineEmits(['completed']);

const capture = props.taskType === 'selfie_doing_something' ? 'user' : 'environment';

// How long the green tick over the photo stays up before moving on.
const DONE_MS = 900;

const submitting = ref(false);
const hasError = ref(false);
const preview = ref(null); // object URL of the photo being sent
const uploaded = ref(false);

let doneTimer = null;

const clearPreview = () => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = null;
};

const onCapture = async (event) => {
    const file = event.target.files[0];
    event.target.value = '';
    if (!file || submitting.value) return;

    submitting.value = true;
    hasError.value = false;

    clearPreview();
    try {
        preview.value = URL.createObjectURL(file);
    } catch {
        preview.value = null; // no preview, the upload still goes ahead
    }

    const formData = new FormData();
    formData.append('photo', file);

    try {
        const response = await window.axios.post(
            route('play.attempts.photo', [props.gameSlug, props.locationId]),
            formData,
        );

        uploaded.value = true;
        // Short sound only: the reward overlay that follows celebrates.
        playSuccess();
        doneTimer = setTimeout(() => emit('completed', response.data.player), DONE_MS);
    } catch (error) {
        console.error('Failed to upload photo', error);
        hasError.value = true;
        clearPreview();
    } finally {
        submitting.value = false;
    }
};

onUnmounted(() => {
    clearTimeout(doneTimer);
    clearPreview();
});
</script>

<template>
    <div class="flex h-dvh w-full flex-col items-center overflow-y-auto bg-gradient-to-b from-teal-400 to-teal-700 px-4 pb-[max(2rem,env(safe-area-inset-bottom))] pt-28">
        <ErrorFlash :show="hasError" />

        <div class="my-auto flex w-full flex-col items-center gap-10">
            <!-- Reference picture as a tilted polaroid. -->
            <div class="w-[70%] max-w-xs -rotate-3 rounded-lg bg-white p-3 pb-10 shadow-2xl">
                <img :src="referenceImage" alt="" class="aspect-square w-full rounded object-cover" draggable="false" />
            </div>

            <!-- The child's own photo while it uploads / once accepted. -->
            <div
                v-if="preview && (submitting || uploaded)"
                class="relative w-40 rotate-3 rounded-lg bg-white p-2 pb-6 shadow-2xl motion-safe:animate-pop"
                aria-hidden="true"
            >
                <img :src="preview" alt="" class="aspect-square w-full rounded object-cover" />

                <span v-if="submitting" class="absolute inset-2 bottom-6 flex items-center justify-center rounded bg-slate-900/35">
                    <svg viewBox="0 0 24 24" class="h-14 w-14 text-white motion-safe:animate-spin" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.3" stroke-width="3" />
                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                    </svg>
                </span>

                <span v-else class="absolute inset-2 bottom-6 flex items-center justify-center rounded bg-green-500/25">
                    <span class="flex h-16 w-16 items-center justify-center rounded-full bg-green-500 text-white shadow-xl ring-4 ring-white motion-safe:animate-pop">
                        <svg viewBox="0 0 24 24" class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12.5 10 17.5 19.5 7" />
                        </svg>
                    </span>
                </span>
            </div>

            <!-- Big round camera button (also shown with a spinner if there is no preview). -->
            <label
                v-else-if="!uploaded"
                aria-label="Vyfotit"
                class="flex h-24 w-24 cursor-pointer items-center justify-center rounded-full bg-orange-500 text-white shadow-xl ring-8 ring-white/60 active:scale-90 motion-safe:transition-transform"
                :class="{ 'pointer-events-none opacity-80': submitting }"
                @click="unlockAudio"
            >
                <svg v-if="submitting" viewBox="0 0 24 24" class="h-12 w-12 motion-safe:animate-spin" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.3" stroke-width="3" />
                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>
                <svg v-else viewBox="0 0 24 24" class="h-14 w-14" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 8.5A2.5 2.5 0 0 1 5.5 6h2l1.6-2.2h5.8L16.5 6h2A2.5 2.5 0 0 1 21 8.5v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17.5Z" class="fill-white/20" />
                    <circle cx="12" cy="13" r="4" />
                    <circle cx="17.8" cy="9" r="0.9" fill="currentColor" stroke="none" />
                </svg>
                <input type="file" accept="image/*" :capture="capture" class="hidden" :disabled="submitting" @change="onCapture" />
            </label>

            <!-- Accepted but without a preview (createObjectURL failed): just the tick. -->
            <span
                v-else
                class="flex h-24 w-24 items-center justify-center rounded-full bg-green-500 text-white shadow-xl ring-8 ring-white/60 motion-safe:animate-pop"
                aria-hidden="true"
            >
                <svg viewBox="0 0 24 24" class="h-14 w-14" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12.5 10 17.5 19.5 7" />
                </svg>
            </span>
        </div>
    </div>
</template>
