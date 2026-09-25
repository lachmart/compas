<script setup>
import { computed } from 'vue';
import { avatarEmoji } from '@/lib/avatars';

const props = defineProps({
    avatar: { type: String, default: null },
    total: { type: Number, required: true },
    // Number of stations already done.
    completed: { type: Number, required: true },
    // Index of the station the child is heading to now.
    current: { type: Number, required: true },
});

defineEmits(['help']);

const emoji = computed(() => avatarEmoji(props.avatar));

// Many stations -> smaller dots so the row still fits beside the animal.
const small = computed(() => props.total > 8);

const stateOf = (index) => {
    if (index < props.completed) return 'done';
    if (index === props.current) return 'current';

    return 'future';
};
</script>

<template>
    <div
        class="pointer-events-none absolute inset-x-0 top-0 z-[1000] flex items-start justify-between gap-3 px-4 pt-[max(1rem,env(safe-area-inset-top))]"
    >
        <!-- The player's animal doubles as the help button. -->
        <button
            type="button"
            aria-label="Nápověda"
            class="pointer-events-auto relative flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-white text-4xl shadow-lg ring-4 ring-white/60 active:scale-95"
            @click="$emit('help')"
        >
            <span aria-hidden="true">{{ emoji }}</span>
            <span class="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-teal-600 text-sm font-bold text-white ring-2 ring-white" aria-hidden="true">?</span>
        </button>

        <div
            class="mt-3 flex min-w-0 flex-wrap items-center justify-end rounded-3xl bg-white/90 px-3 py-2 shadow-lg"
            :class="small ? 'gap-1' : 'gap-1.5'"
            aria-hidden="true"
        >
            <template v-for="index in total" :key="index">
                <svg
                    v-if="stateOf(index - 1) === 'done'"
                    viewBox="0 0 24 24"
                    :class="small ? 'h-4 w-4' : 'h-6 w-6'"
                >
                    <path
                        d="M12 2.5l2.9 6 6.6.8-4.9 4.5 1.3 6.5L12 17l-5.9 3.3 1.3-6.5-4.9-4.5 6.6-.8z"
                        class="fill-amber-400 stroke-amber-600"
                        stroke-width="1.5"
                        stroke-linejoin="round"
                    />
                </svg>
                <span
                    v-else-if="stateOf(index - 1) === 'current'"
                    class="rounded-full border-orange-500 bg-orange-200 motion-safe:animate-pulse"
                    :class="small ? 'h-4 w-4 border-[3px]' : 'h-6 w-6 border-4'"
                ></span>
                <span
                    v-else
                    class="rounded-full border-slate-400"
                    :class="small ? 'h-3 w-3 border-2' : 'h-4 w-4 border-2'"
                ></span>
            </template>
        </div>
    </div>
</template>
