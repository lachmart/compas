<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TrackingMap from '@/Components/Admin/TrackingMap.vue';
import PlayerDetail from '@/Components/Admin/PlayerDetail.vue';
import { getEcho } from '@/lib/echo';
import { applyPosition, applyProgress, formatTime, isStale, progressLabel, progressMessage } from '@/lib/tracking';

const props = defineProps({
    game: Object,
    locations: Array,
    players: Array,
});

const livePlayers = ref(props.players);
const now = ref(Date.now());
const selectedPlayerId = ref(null);
const detailVersion = ref(0);
const toasts = ref([]);
const echoUnavailable = ref(false);
let toastId = 0;
let nowTimer = null;
let echo = null;

const channelName = `games.${props.game.id}.tracking`;

const pushToast = (message) => {
    const id = ++toastId;
    toasts.value.push({ id, message });
    setTimeout(() => {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }, 5000);
};

onMounted(() => {
    nowTimer = setInterval(() => {
        now.value = Date.now();
    }, 15000);

    echo = getEcho();

    if (!echo) {
        echoUnavailable.value = true;

        return;
    }

    echo
        .private(channelName)
        .listen('.player.position', (payload) => {
            livePlayers.value = applyPosition(livePlayers.value, payload);
        })
        .listen('.player.progressed', (payload) => {
            livePlayers.value = applyProgress(livePlayers.value, payload);
            pushToast(progressMessage(payload));
            if (payload.player.id === selectedPlayerId.value) detailVersion.value++;
        });
});

onBeforeUnmount(() => {
    clearInterval(nowTimer);
    if (echo) echo.leave(channelName);
});
</script>

<template>
    <Head :title="`Sledování – ${game.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Sledování: {{ game.name }}</h2>
                <Link :href="route('admin.games.edit', game.id)" class="text-sm text-indigo-600">← Zpět na úpravu hry</Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 pt-4">
            <p v-if="echoUnavailable" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                Živé sledování není dostupné (Reverb není nakonfigurovaný) — data se aktualizují jen po obnovení stránky.
            </p>
        </div>

        <div class="mx-auto grid max-w-7xl gap-4 p-4 lg:grid-cols-[1fr_22rem]">
            <div class="h-[70vh] overflow-hidden rounded-lg bg-white shadow-sm">
                <TrackingMap
                    :locations="locations"
                    :players="livePlayers"
                    :now="now"
                    @select="selectedPlayerId = $event"
                />
            </div>

            <div class="space-y-4">
                <div class="rounded-lg bg-white p-4 shadow-sm">
                    <h3 class="mb-2 font-medium text-gray-800">Hráči ({{ livePlayers.length }})</h3>
                    <ul class="divide-y">
                        <li v-for="player in livePlayers" :key="player.id">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between py-2 text-left"
                                :class="{ 'text-gray-400': isStale(player, now) }"
                                @click="selectedPlayerId = player.id"
                            >
                                <span class="flex items-center gap-2">
                                    <span
                                        class="inline-block h-3 w-3 rounded-full"
                                        :style="{ backgroundColor: isStale(player, now) ? '#9ca3af' : player.color }"
                                    ></span>
                                    {{ player.nickname }}
                                </span>
                                <span class="text-right text-sm">
                                    {{ progressLabel(player) }}
                                    <span class="block text-xs">
                                        {{ isStale(player, now) ? 'naposledy viděn v ' : '' }}{{ formatTime(player.last_seen_at) }}
                                    </span>
                                </span>
                            </button>
                        </li>
                        <li v-if="livePlayers.length === 0" class="py-2 text-sm text-gray-500">Zatím se nikdo nepřipojil.</li>
                    </ul>
                </div>

                <div v-if="selectedPlayerId" class="rounded-lg bg-white p-4 shadow-sm">
                    <PlayerDetail
                        :game-id="game.id"
                        :player-id="selectedPlayerId"
                        :version="detailVersion"
                        @close="selectedPlayerId = null"
                    />
                </div>
            </div>
        </div>

        <div class="fixed bottom-4 right-4 z-[1000] space-y-2">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="rounded-lg bg-gray-900 px-4 py-2 text-sm text-white shadow-lg"
            >
                {{ toast.message }}
            </div>
        </div>
    </AuthenticatedLayout>
</template>
