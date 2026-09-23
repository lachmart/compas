<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PlayerCard from '@/Components/Admin/PlayerCard.vue';
import PlayerDetail from '@/Components/Admin/PlayerDetail.vue';
import SharePanel from '@/Components/Admin/SharePanel.vue';
import TrackingMap from '@/Components/Admin/TrackingMap.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import SegmentedTabs from '@/Components/UI/SegmentedTabs.vue';
import { getEcho } from '@/lib/echo';
import { sortPlayers } from '@/lib/playerOrder';
import { applyPosition, applyProgress, diffProgress, isStale, progressMessage } from '@/lib/tracking';

const props = defineProps({
    game: Object,
    locations: Array,
    players: Array,
    play_url: String,
});

const POLL_INTERVAL_MS = 5000;

const livePlayers = ref(props.players);
const now = ref(Date.now());
const selectedPlayerId = ref(null);
const detailVersion = ref(0);
const toasts = ref([]);
const pollFailed = ref(false);
let toastId = 0;
let nowTimer = null;
let pollTimer = null;
let pollInFlight = false;
let echo = null;
// Presentation only: whether updates arrive over Reverb or by polling.
const usingEcho = ref(false);

const channelName = `games.${props.game.id}.tracking`;

const pushToast = (message) => {
    const id = ++toastId;
    toasts.value.push({ id, message });
    setTimeout(() => {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }, 5000);
};

const onProgress = (payload) => {
    pushToast(progressMessage(payload));
    if (payload.player.id === selectedPlayerId.value) detailVersion.value++;
};

// Without Reverb (e.g. basic webhosting) the page polls the player list and
// derives the same progress events by diffing snapshots.
const poll = async () => {
    if (pollInFlight || document.hidden) return;

    pollInFlight = true;
    try {
        const response = await window.axios.get(route('admin.games.tracking.players', props.game.id));
        const next = response.data.players;
        diffProgress(livePlayers.value, next).forEach(onProgress);
        livePlayers.value = next;
        pollFailed.value = false;
    } catch (error) {
        console.error('Failed to poll tracked players', error);
        pollFailed.value = true;
    } finally {
        pollInFlight = false;
    }
};

onMounted(() => {
    nowTimer = setInterval(() => {
        now.value = Date.now();
    }, 15000);

    echo = getEcho();
    usingEcho.value = Boolean(echo);

    if (!echo) {
        pollTimer = setInterval(poll, POLL_INTERVAL_MS);

        return;
    }

    echo
        .private(channelName)
        .listen('.player.position', (payload) => {
            livePlayers.value = applyPosition(livePlayers.value, payload);
        })
        .listen('.player.progressed', (payload) => {
            livePlayers.value = applyProgress(livePlayers.value, payload);
            onProgress(payload);
        });
});

// --- Presentation -----------------------------------------------------------

const tabs = computed(() => [
    { label: 'Editor', href: route('admin.games.edit', props.game.id), active: false },
    { label: 'Sledování', href: route('admin.games.tracking', props.game.id), active: true },
]);

// 'live' = Reverb, 'polling' = polling fallback OK, 'error' = last poll failed.
const connectionState = computed(() => {
    if (usingEcho.value) return 'live';

    return pollFailed.value ? 'error' : 'polling';
});

const selectedLivePlayer = computed(() => livePlayers.value.find((p) => p.id === selectedPlayerId.value) ?? null);

const sortedPlayers = computed(() => sortPlayers(livePlayers.value, now.value));

const stats = computed(() => {
    const players = livePlayers.value;

    return [
        { label: 'Hráči', value: players.length },
        { label: 'Právě hrají', value: players.filter((p) => !p.finished_at && !isStale(p, now.value)).length },
        { label: 'Dokončili', value: players.filter((p) => p.finished_at).length },
    ];
});

const mapRef = ref(null);
const panelEl = ref(null);

// Every panel switch (list ↔ detail) starts at the top.
watch(selectedPlayerId, async () => {
    await nextTick();
    if (panelEl.value) panelEl.value.scrollTop = 0;
});

onBeforeUnmount(() => {
    clearInterval(nowTimer);
    clearInterval(pollTimer);
    if (echo) echo.leave(channelName);
});
</script>

<template>
    <Head :title="`Sledování – ${game.name}`" />

    <AuthenticatedLayout full-bleed>
        <template #breadcrumb>
            <Link :href="route('admin.games.index')" class="hover:text-teal-700">Moje hry</Link>
            <span class="text-slate-300" aria-hidden="true">/</span>
            <span class="max-w-[40vw] truncate font-medium text-slate-800 sm:max-w-xs">{{ game.name }}</span>
        </template>

        <template #tabs>
            <SegmentedTabs :tabs="tabs" />
        </template>

        <div class="flex min-h-0 min-w-0 flex-1 flex-col lg:flex-row">
            <aside
                ref="panelEl"
                class="min-h-0 flex-1 overflow-y-auto border-slate-200 bg-white lg:w-[420px] lg:flex-none lg:shrink-0 lg:border-r"
            >
                <div v-if="selectedPlayerId" class="space-y-4 p-5">
                    <button
                        type="button"
                        class="-ml-1 inline-flex items-center gap-1 rounded-lg px-1 py-0.5 text-sm font-medium text-slate-600 transition hover:text-teal-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                        @click="selectedPlayerId = null"
                    >
                        <span aria-hidden="true">←</span> Zpět na hráče
                    </button>

                    <PlayerDetail
                        :game-id="game.id"
                        :player-id="selectedPlayerId"
                        :version="detailVersion"
                        :now="now"
                        :live-player="selectedLivePlayer"
                    />
                </div>

                <div v-else class="space-y-5 p-5">
                    <div>
                        <h1 class="break-words text-xl font-bold leading-snug text-slate-900">{{ game.name }}</h1>

                        <p class="mt-1.5 flex items-center gap-2 text-sm" role="status">
                            <template v-if="connectionState === 'live'">
                                <span class="relative flex h-2.5 w-2.5" aria-hidden="true">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75" />
                                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500" />
                                </span>
                                <span class="font-medium text-green-700">Živě</span>
                            </template>
                            <template v-else-if="connectionState === 'polling'">
                                <span class="h-2.5 w-2.5 rounded-full bg-teal-500" aria-hidden="true" />
                                <span class="text-slate-600">Obnovuje se každých 5 s</span>
                            </template>
                            <template v-else>
                                <span class="h-2.5 w-2.5 rounded-full bg-orange-500" aria-hidden="true" />
                                <span class="font-medium text-orange-700">Spojení přerušeno — zkouším znovu…</span>
                            </template>
                        </p>
                    </div>

                    <dl class="grid grid-cols-3 gap-2">
                        <div
                            v-for="stat in stats"
                            :key="stat.label"
                            class="rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-slate-900/5"
                        >
                            <dt class="text-xs text-slate-500">{{ stat.label }}</dt>
                            <dd class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ stat.value }}</dd>
                        </div>
                    </dl>

                    <section v-if="sortedPlayers.length" aria-labelledby="players-heading">
                        <h2 id="players-heading" class="mb-2 px-1 text-sm font-semibold text-slate-800">Hráči</h2>
                        <ul class="space-y-0.5">
                            <li v-for="player in sortedPlayers" :key="player.id">
                                <PlayerCard
                                    :player="player"
                                    :now="now"
                                    :selected="player.id === selectedPlayerId"
                                    @select="selectedPlayerId = $event"
                                />
                            </li>
                        </ul>
                    </section>

                    <EmptyState
                        v-else
                        title="Zatím se nikdo nepřipojil"
                        text="Pošli dětem odkaz nebo jim ukaž QR kód."
                    >
                        <template #icon>
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                        </template>
                        <div v-if="play_url" class="mt-6 w-full text-left">
                            <SharePanel :play-url="play_url" :slug="game.slug" :published="game.status === 'published'" />
                        </div>
                        <template #actions>
                            <Button size="sm" variant="secondary" :href="route('admin.games.edit', game.id)">Otevřít editor</Button>
                        </template>
                    </EmptyState>
                </div>
            </aside>

            <div class="relative isolate order-first h-[45vh] shrink-0 border-b border-slate-200 lg:order-none lg:h-auto lg:flex-1 lg:border-b-0">
                <TrackingMap
                    ref="mapRef"
                    :locations="locations"
                    :players="livePlayers"
                    :now="now"
                    @select="selectedPlayerId = $event"
                />

                <div class="pointer-events-none absolute inset-x-0 top-3 z-[1000] flex justify-center px-14">
                    <p
                        v-if="locations.length === 0"
                        class="flex items-center gap-2 rounded-full bg-slate-900/85 px-4 py-2 text-center text-sm font-medium text-white shadow-lg backdrop-blur"
                    >
                        <span class="h-2 w-2 shrink-0 rounded-full bg-orange-400" aria-hidden="true" />
                        Hra zatím nemá žádná stanoviště
                    </p>
                </div>

                <button
                    v-if="locations.length || livePlayers.length"
                    type="button"
                    class="absolute right-3 top-3 z-[1000] flex h-[34px] w-[34px] items-center justify-center rounded-lg bg-white text-slate-600 shadow ring-1 ring-slate-900/15 transition hover:bg-slate-50 hover:text-teal-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                    title="Zobrazit vše"
                    aria-label="Zobrazit všechna stanoviště a hráče"
                    @click="mapRef?.fitAll()"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M3.28 2.22a.75.75 0 0 0-1.06 1.06L5.44 6.5H2.75a.75.75 0 0 0 0 1.5h4.5A.75.75 0 0 0 8 7.25v-4.5a.75.75 0 0 0-1.5 0v2.69L3.28 2.22ZM13.5 2.75a.75.75 0 0 0-1.5 0v4.5c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-2.69l3.22-3.22a.75.75 0 0 0-1.06-1.06L13.5 5.44V2.75ZM3.28 17.78l3.22-3.22v2.69a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.69l-3.22 3.22a.75.75 0 1 0 1.06 1.06ZM13.5 14.56l3.22 3.22a.75.75 0 1 0 1.06-1.06l-3.22-3.22h2.69a.75.75 0 0 0 0-1.5h-4.5a.75.75 0 0 0-.75.75v4.5a.75.75 0 0 0 1.5 0v-2.69Z" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="pointer-events-none fixed bottom-4 right-4 z-[1100] flex w-[min(22rem,calc(100vw-2rem))] flex-col gap-2" aria-live="polite">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="rounded-xl border-l-4 border-teal-500 bg-white px-4 py-3 text-sm font-medium text-slate-800 shadow-lg ring-1 ring-slate-900/10"
            >
                {{ toast.message }}
            </div>
        </div>
    </AuthenticatedLayout>
</template>
