<script setup>
import { computed, ref, watch } from 'vue';
import TaskTypeIcon from '@/Components/Admin/TaskTypeIcon.vue';
import Button from '@/Components/UI/Button.vue';
import Spinner from '@/Components/UI/Spinner.vue';
import { avatarEmoji } from '@/lib/avatars';
import { relativeTime } from '@/lib/format';
import { taskTypeLabel } from '@/lib/taskTypes';
import { formatTime, isStale, progressLabel } from '@/lib/tracking';

const props = defineProps({
    gameId: { type: Number, required: true },
    playerId: { type: Number, required: true },
    // Bumped by the parent whenever this player progresses, so the attempt
    // list refreshes live without polling.
    version: { type: Number, default: 0 },
    // Ticker from the parent for "naposledy před …".
    now: { type: Number, default: () => Date.now() },
    // The player's entry from the live list (pings/polls keep it fresh). The
    // fetched detail only refreshes on progress, so the header reads live
    // fields from here and falls back to the fetched snapshot.
    livePlayer: { type: Object, default: null },
    // True while the parent hides/unhides this player.
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['hide', 'unhide', 'delete']);

const detail = ref(null);
const loadError = ref(false);
const flagError = ref(false);

// Guards against out-of-order responses: only the response from the most
// recently started request is applied, so quickly switching players can't
// have an earlier, slower response overwrite a later one.
let requestToken = 0;

const headerPlayer = computed(() => props.livePlayer ?? detail.value?.player ?? null);

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
    <div class="space-y-5">
        <div v-if="detail && headerPlayer" class="flex items-start gap-3">
            <span
                class="mt-1.5 h-3.5 w-3.5 shrink-0 rounded-full ring-2 ring-white"
                :style="{ backgroundColor: isStale(headerPlayer, now) ? '#94a3b8' : headerPlayer.color }"
                aria-hidden="true"
            />
            <div class="min-w-0">
                <h2 class="break-words text-xl font-bold leading-snug text-slate-900">
                    {{ avatarEmoji(headerPlayer.avatar) }} {{ headerPlayer.nickname }}
                </h2>
                <p class="mt-0.5 text-sm text-slate-500">
                    <template v-if="headerPlayer.finished_at">
                        <span class="font-semibold text-green-700">✓ dokončil(a)</span>
                        v {{ formatTime(headerPlayer.finished_at) }}
                    </template>
                    <template v-else>
                        Stanoviště {{ progressLabel(headerPlayer) }} · naposledy {{ relativeTime(headerPlayer.last_seen_at, now) }}
                    </template>
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Button
                        v-if="headerPlayer.hidden"
                        size="sm"
                        variant="secondary"
                        :processing="busy"
                        @click="emit('unhide', headerPlayer)"
                    >Zobrazit</Button>
                    <Button
                        v-else
                        size="sm"
                        variant="secondary"
                        :processing="busy"
                        @click="emit('hide', headerPlayer)"
                    >Skrýt</Button>
                    <Button size="sm" variant="danger" @click="emit('delete', headerPlayer)">Smazat</Button>
                </div>
            </div>
        </div>

        <div v-else-if="!loadError" class="flex items-center gap-2 py-6 text-sm text-slate-500">
            <Spinner class="h-4 w-4" /> Načítám…
        </div>

        <p v-if="loadError" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">Nepodařilo se načíst data. Zkus to znovu.</p>
        <p v-if="flagError" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">Označení fotky se nepodařilo uložit.</p>

        <section v-if="detail" aria-labelledby="attempts-heading">
            <h3 id="attempts-heading" class="mb-3 text-sm font-semibold text-slate-800">Stanoviště</h3>

            <ol v-if="detail.attempts.length" class="relative space-y-4">
                <li v-for="(attempt, index) in detail.attempts" :key="attempt.id" class="relative flex gap-3">
                    <span
                        v-if="index < detail.attempts.length - 1"
                        class="absolute left-[15px] top-8 -bottom-4 w-px bg-slate-200"
                        aria-hidden="true"
                    />
                    <span
                        class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                        :class="attempt.completed_at ? 'bg-teal-600 text-white' : 'bg-white text-teal-700 ring-2 ring-teal-600'"
                    >{{ attempt.location_position }}</span>

                    <div class="min-w-0 flex-1 pb-1">
                        <p class="break-words font-semibold text-slate-900">
                            {{ attempt.location_name || `Stanoviště ${attempt.location_position}` }}
                        </p>
                        <p class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
                            <TaskTypeIcon :type="attempt.task_type" class="h-4 w-4 shrink-0" />
                            {{ taskTypeLabel(attempt.task_type) }}
                        </p>
                        <dl class="mt-1.5 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-slate-600">
                            <div class="flex gap-1">
                                <dt class="text-slate-400">Příchod</dt>
                                <dd class="tabular-nums">{{ attempt.arrived_at ? formatTime(attempt.arrived_at) : '—' }}</dd>
                            </div>
                            <div class="flex gap-1">
                                <dt class="text-slate-400">Splněno</dt>
                                <dd class="tabular-nums">{{ attempt.completed_at ? formatTime(attempt.completed_at) : '—' }}</dd>
                            </div>
                        </dl>

                        <div v-if="attempt.photo_url" class="mt-3 space-y-2">
                            <a :href="attempt.photo_url" target="_blank" rel="noopener" class="block w-fit">
                                <img
                                    :src="attempt.photo_url"
                                    alt="Fotka ze stanoviště"
                                    class="h-32 w-32 rounded-xl object-cover ring-1 ring-slate-900/10"
                                    :class="{ 'opacity-40 ring-2 ring-red-500': attempt.flagged_invalid }"
                                />
                            </a>
                            <p v-if="attempt.flagged_invalid" class="text-xs font-medium text-red-700">Označeno jako neplatná</p>
                            <Button size="sm" variant="ghost" @click="toggleFlag(attempt)">
                                {{ attempt.flagged_invalid ? 'Zrušit označení' : 'Označit jako neplatnou' }}
                            </Button>
                        </div>
                    </div>
                </li>
            </ol>

            <p v-else class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500 ring-1 ring-slate-900/5">
                Zatím nedorazil(a) na žádné stanoviště.
            </p>
        </section>
    </div>
</template>
