<script setup>
import { useId } from 'vue';

// Shared frame of the player's dialogs (permissions, help): a dimmed
// backdrop with a card that rises from the bottom on phones.
defineProps({
    title: { type: String, required: true },
    // Accessible name of the ✕ button.
    closeLabel: { type: String, default: 'Zavřít' },
});
const emit = defineEmits(['close']);

const titleId = useId();
</script>

<template>
    <div class="fixed inset-0 z-[1300] flex items-end justify-center bg-slate-900/40 p-4 sm:items-center" role="dialog" aria-modal="true" :aria-labelledby="titleId">
        <div class="max-h-full w-full max-w-md overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <h2 :id="titleId" class="text-2xl font-bold text-teal-900">{{ title }}</h2>
                <button
                    type="button"
                    :aria-label="closeLabel"
                    class="-mr-2 -mt-2 flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100"
                    @click="emit('close')"
                >
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                        <path d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>

            <slot />
        </div>
    </div>
</template>
