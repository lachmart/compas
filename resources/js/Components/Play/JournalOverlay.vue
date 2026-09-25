<script setup>
import { onMounted, ref } from 'vue';
import InfoCard from '@/Components/Play/InfoCard.vue';
import { isSessionGone, reloadForGoneSession } from '@/lib/playSession';

// The stations this player has passed: their text boards and the player's
// own photos, to look at again.
const props = defineProps({
    gameSlug: { type: String, required: true },
});
const emit = defineEmits(['close']);

const entries = ref(null); // null while loading
const failed = ref(false);
const reading = ref(null);

const load = async () => {
    failed.value = false;

    try {
        const { data } = await window.axios.get(route('play.journal', props.gameSlug));
        entries.value = data.entries;
    } catch (error) {
        if (isSessionGone(error)) {
            reloadForGoneSession();

            return;
        }

        console.error('Failed to load the journal', error);
        failed.value = true;
    }
};

onMounted(load);

const entryTitle = (entry) => entry.title || `Stanoviště ${entry.position}`;
</script>

<template>
    <div class="fixed inset-0 z-[1200]">
        <InfoCard v-if="reading" :info="reading" back @continue="reading = null" />

        <div v-else class="h-dvh w-full overflow-y-auto bg-gradient-to-b from-emerald-100 via-teal-50 to-sky-100 px-4 pb-[max(2rem,env(safe-area-inset-bottom))] pt-[max(1.5rem,env(safe-area-inset-top))]">
            <div class="mx-auto flex w-full max-w-xl items-center justify-between gap-4">
                <h1 class="text-2xl font-bold text-teal-900">Prošlá stanoviště</h1>
                <button
                    type="button"
                    aria-label="Zavřít"
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-teal-800 shadow-lg ring-1 ring-teal-900/5 active:scale-95"
                    @click="emit('close')"
                >
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true">
                        <path d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>

            <p v-if="failed" class="mx-auto mt-8 max-w-xl text-center text-lg text-slate-700">
                Nepodařilo se načíst.
                <button type="button" class="font-semibold text-teal-700 underline" @click="load">Zkusit znovu</button>
            </p>

            <div v-else-if="entries === null" class="mt-16 flex justify-center" aria-label="Načítám">
                <span class="h-10 w-10 animate-spin rounded-full border-4 border-teal-200 border-t-teal-600"></span>
            </div>

            <p v-else-if="entries.length === 0" class="mx-auto mt-8 max-w-xl text-center text-lg text-slate-700">
                Zatím tu nic není.
            </p>

            <ul v-else class="mx-auto mt-6 w-full max-w-xl space-y-3">
                <li v-for="entry in entries" :key="entry.id">
                    <button
                        type="button"
                        class="flex w-full items-center gap-4 rounded-3xl bg-white p-3 text-left shadow-lg ring-1 ring-teal-900/5 active:scale-[0.98] motion-safe:transition-transform"
                        @click="reading = entry"
                    >
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-teal-600 text-lg font-bold text-white">{{ entry.position }}</span>
                        <span class="min-w-0 flex-1 break-words text-lg font-semibold text-slate-900">{{ entryTitle(entry) }}</span>
                        <img v-if="entry.photo || entry.images.length" :src="entry.photo ?? entry.images[0]" alt="" loading="lazy" class="h-16 w-16 shrink-0 rounded-2xl object-cover" />
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
