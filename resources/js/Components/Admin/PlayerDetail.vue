<script setup>
import { ref, watch } from 'vue';
import { formatTime, progressLabel } from '@/lib/tracking';

const props = defineProps({
    gameId: { type: Number, required: true },
    playerId: { type: Number, required: true },
    // Bumped by the parent whenever this player progresses, so the attempt
    // list refreshes live without polling.
    version: { type: Number, default: 0 },
});
const emit = defineEmits(['close']);

const detail = ref(null);
const loadError = ref(false);
const flagError = ref(false);

const TASK_ICONS = {
    arrival_only: '📍',
    pick_image: '🖼️',
    photo_of_something: '📷',
    selfie_doing_something: '🤳',
};

// Guards against out-of-order responses: only the response from the most
// recently started request is applied, so quickly switching players can't
// have an earlier, slower response overwrite a later one.
let requestToken = 0;

const load = async () => {
    const token = ++requestToken;
    loadError.value = false;

    try {
        const response = await window.axios.get(route('admin.games.players.show', [props.gameId, props.playerId]));
        if (token !== requestToken) return;
        detail.value = response.data;
    } catch (error) {
        if (token !== requestToken) return;
        console.error('Failed to load player detail', error);
        loadError.value = true;
    }
};

const toggleFlag = async (attempt) => {
    flagError.value = false;
    try {
        const response = await window.axios.patch(
            route('admin.games.players.attempts.flag', [props.gameId, props.playerId, attempt.id]),
            { flagged_invalid: !attempt.flagged_invalid },
        );
        attempt.flagged_invalid = response.data.flagged_invalid;
    } catch (error) {
        console.error('Failed to flag photo', error);
        flagError.value = true;
    }
};

watch(
    () => props.playerId,
    () => {
        detail.value = null;
    },
);

watch(() => [props.playerId, props.version], load, { immediate: true });
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 v-if="detail" class="flex items-center gap-2 text-lg font-medium text-gray-800">
                <span class="inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: detail.player.color }"></span>
                {{ detail.player.nickname }}
                <span class="text-sm text-gray-500">{{ progressLabel(detail.player) }}</span>
            </h3>
            <button type="button" class="text-gray-500" @click="emit('close')">✕</button>
        </div>

        <p v-if="loadError" class="text-sm text-red-600">Nepodařilo se načíst data. Zkus to znovu.</p>
        <p v-if="flagError" class="text-sm text-red-600">Označení fotky se nepodařilo uložit.</p>

        <table v-if="detail" class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="py-1">#</th>
                    <th>Příchod</th>
                    <th>Splněno</th>
                    <th>Fotka</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="attempt in detail.attempts" :key="attempt.id" class="border-t align-top">
                    <td class="py-2">{{ TASK_ICONS[attempt.task_type] }} {{ attempt.location_position }}</td>
                    <td>{{ attempt.arrived_at ? formatTime(attempt.arrived_at) : '—' }}</td>
                    <td>{{ attempt.completed_at ? formatTime(attempt.completed_at) : '—' }}</td>
                    <td>
                        <div v-if="attempt.photo_url" class="space-y-1">
                            <a :href="attempt.photo_url" target="_blank" rel="noopener">
                                <img
                                    :src="attempt.photo_url"
                                    class="h-16 w-16 rounded object-cover"
                                    :class="{ 'opacity-40 ring-2 ring-red-500': attempt.flagged_invalid }"
                                />
                            </a>
                            <button
                                type="button"
                                class="text-xs"
                                :class="attempt.flagged_invalid ? 'text-gray-600' : 'text-red-600'"
                                @click="toggleFlag(attempt)"
                            >
                                {{ attempt.flagged_invalid ? 'Zrušit označení' : 'Označit jako neplatnou' }}
                            </button>
                        </div>
                        <span v-else class="text-gray-400">—</span>
                    </td>
                </tr>
                <tr v-if="detail.attempts.length === 0">
                    <td colspan="4" class="py-2 text-gray-500">Zatím žádné stanoviště.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
