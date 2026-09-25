<script setup>
import { computed } from 'vue';
import { unlockAudio } from '@/lib/feedback';

// The station's text board (nature-trail style), read right after arriving.
const props = defineProps({
    // { title, text, images, photo? } — plain text, rendered escaped (no
    // HTML); photo = the player's own task photo (journal only).
    info: { type: Object, required: true },
    // Re-reading from the journal: the button goes back instead of on.
    back: { type: Boolean, default: false },
});
const emit = defineEmits(['continue']);

// Blank lines separate paragraphs; single line breaks stay inside one.
const paragraphs = computed(() =>
    (props.info.text ?? '')
        .split(/\n\s*\n/)
        .map((paragraph) => paragraph.trim())
        .filter(Boolean),
);

const next = () => {
    // A tap: unlock sound for what follows (iOS gesture rule).
    unlockAudio();
    emit('continue');
};
</script>

<template>
    <!-- In the game the player HUD sits on top (pt-28); the journal covers it. -->
    <div
        class="h-dvh w-full overflow-y-auto bg-gradient-to-b from-emerald-100 via-teal-50 to-sky-100 px-4 pb-[max(2rem,env(safe-area-inset-bottom))]"
        :class="back ? 'pt-[max(1.5rem,env(safe-area-inset-top))]' : 'pt-28'"
    >
        <article class="mx-auto w-full max-w-xl rounded-3xl bg-white p-6 shadow-xl ring-1 ring-teal-900/5 sm:p-8">
            <h1 v-if="info.title" class="break-words text-2xl font-bold text-teal-900">{{ info.title }}</h1>

            <div v-if="paragraphs.length" class="space-y-4 text-lg leading-relaxed text-slate-800" :class="{ 'mt-4': info.title }">
                <p v-for="(paragraph, index) in paragraphs" :key="index" class="whitespace-pre-line break-words">{{ paragraph }}</p>
            </div>

            <div v-if="info.photo" :class="{ 'mt-6': info.title || paragraphs.length }">
                <h2 class="mb-3 text-lg font-semibold text-teal-900">Tvoje fotka</h2>
                <img :src="info.photo" alt="Tvoje fotka" class="w-full rounded-2xl object-cover ring-4 ring-orange-300" />
            </div>

            <div v-if="info.images?.length" class="space-y-4" :class="{ 'mt-6': info.title || paragraphs.length || info.photo }">
                <img
                    v-for="(image, index) in info.images"
                    :key="index"
                    :src="image"
                    alt=""
                    loading="lazy"
                    class="w-full rounded-2xl object-cover ring-1 ring-slate-900/5"
                />
            </div>
        </article>

        <div class="mx-auto mt-6 flex max-w-xl justify-center">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-full px-8 py-4 text-xl font-bold shadow-xl ring-4 ring-white active:scale-95 motion-safe:transition-transform"
                :class="back ? 'bg-white text-teal-800' : 'bg-orange-500 text-white'"
                @click="next"
            >
                <svg v-if="back" viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M19 12H5M11 6l-6 6 6 6" />
                </svg>
                {{ back ? 'Zpět' : 'Pokračovat' }}
                <svg v-if="!back" viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 12h14M13 6l6 6-6 6" />
                </svg>
            </button>
        </div>
    </div>
</template>
