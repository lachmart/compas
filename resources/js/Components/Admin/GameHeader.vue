<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Badge from '@/Components/UI/Badge.vue';
import { pluralize } from '@/lib/format';
import { gameStatusLabel, gameStatusTone } from '@/lib/gameStatus';

const props = defineProps({
    game: { type: Object, required: true },
    locationsCount: { type: Number, required: true },
    playersCount: { type: Number, default: 0 },
});

const toggling = ref(false);

const isPublished = computed(() => props.game.status === 'published');
const cannotPublish = computed(() => !isPublished.value && props.locationsCount === 0);

const summary = computed(() => {
    const parts = [pluralize(props.locationsCount, 'stanoviště', 'stanoviště', 'stanovišť')];
    if (props.playersCount > 0) parts.push(pluralize(props.playersCount, 'hráč', 'hráči', 'hráčů'));

    return parts.join(' · ');
});

const togglePublish = () => {
    if (cannotPublish.value || toggling.value) return;

    toggling.value = true;
    router.patch(route(isPublished.value ? 'admin.games.unpublish' : 'admin.games.publish', props.game.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            toggling.value = false;
        },
    });
};
</script>

<template>
    <div class="space-y-4">
        <div>
            <div class="flex items-start justify-between gap-3">
                <h1 class="min-w-0 break-words text-xl font-bold leading-snug text-slate-900">{{ game.name }}</h1>
                <Badge :tone="gameStatusTone(game.status)" class="mt-1 shrink-0">{{ gameStatusLabel(game.status) }}</Badge>
            </div>
            <p class="mt-0.5 text-sm text-slate-500">{{ summary }}</p>
        </div>

        <div class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 px-4 py-3 ring-1 ring-slate-900/5">
            <div class="min-w-0">
                <p id="publish-label" class="text-sm font-semibold text-slate-800">Publikováno</p>
                <p class="text-xs text-slate-500">
                    <template v-if="cannotPublish">Nejdřív přidej stanoviště</template>
                    <template v-else-if="isPublished">Děti můžou hru hrát</template>
                    <template v-else>Hru zatím vidíš jen ty</template>
                </p>
            </div>

            <button
                type="button"
                role="switch"
                aria-labelledby="publish-label"
                :aria-checked="isPublished"
                :disabled="cannotPublish || toggling"
                :title="cannotPublish ? 'Nejdřív přidej stanoviště' : undefined"
                class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                :class="isPublished ? 'bg-teal-600' : 'bg-slate-300'"
                @click="togglePublish"
            >
                <span
                    class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"
                    :class="isPublished ? 'translate-x-6' : 'translate-x-1'"
                />
            </button>
        </div>
    </div>
</template>
