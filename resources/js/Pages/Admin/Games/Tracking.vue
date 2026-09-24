<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import PlayerCard from '@/Components/Admin/PlayerCard.vue';
import PlayerDetail from '@/Components/Admin/PlayerDetail.vue';
import SharePanel from '@/Components/Admin/SharePanel.vue';
import TrackingMap from '@/Components/Admin/TrackingMap.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import SegmentedTabs from '@/Components/UI/SegmentedTabs.vue';
import { avatarEmoji } from '@/lib/avatars';
import { getEcho } from '@/lib/echo';
import { pluralize } from '@/lib/format';
import { sortPlayers } from '@/lib/playerOrder';
import { inactiveIds, reconcilePoll, splitPlayers } from '@/lib/playerVisibility';
import { applyPosition, applyProgress, isStale, progressMessage } from '@/lib/tracking';

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
// The Pusher connection state while Echo is used ('initialized', 'connecting',
// 'connected', 'unavailable', 'failed', 'disconnected').
const socketState = ref('initialized');
let socketConnection = null;

const channelName = `games.${props.game.id}.tracking`;

// Bumped whenever a local hide/unhide/delete succeeds, so a poll response that
// was in flight meanwhile (and may predate the change) is discarded.
let mutationVersion = 0;
// Players deleted on this page; they can never legitimately reappear.
const deletedIds = new Set();

const pushToast = (message, tone = 'info') => {
    const id = ++toastId;
    toasts.value.push({ id, message, tone });
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
const startPolling = () => {
    if (!pollTimer) pollTimer = setInterval(poll, POLL_INTERVAL_MS);
};

const stopPolling = () => {
    clearInterval(pollTimer);
    pollTimer = null;
};

// While the WebSocket is down, fall back to polling; stop once it reconnects.
const onSocketStateChange = ({ current }) => {
    socketState.value = current;
    if (current === 'connected') stopPolling();
    else if (current === 'unavailable' || current === 'failed') startPolling();
};

const poll = async () => {
    if (pollInFlight || document.hidden) return;

    pollInFlight = true;
    const versionAtRequest = mutationVersion;
    try {
        const response = await window.axios.get(route('admin.games.tracking.players', props.game.id));
        const { players, events } = reconcilePoll(livePlayers.value, response.data.players, {
            stale: versionAtRequest !== mutationVersion,
            deletedIds,
        });
        events.forEach(onProgress);
        livePlayers.value = players;
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
        startPolling();

        return;
    }

    socketConnection = echo.connector?.pusher?.connection ?? null;
    if (socketConnection) {
        socketConnection.bind('state_changed', onSocketStateChange);
        onSocketStateChange({ current: socketConnection.state });
    }

    echo
        .private(channelName)
        .listen('.player.position', (payload) => {
            livePlayers.value = applyPosition(livePlayers.value, payload);
        })
        .listen('.player.progressed', (payload) => {
            if (deletedIds.has(payload.player.id)) return;
            livePlayers.value = applyProgress(livePlayers.value, payload);
            onProgress(payload);
        });
});

// --- Presentation -----------------------------------------------------------

const tabs = computed(() => [
    { label: 'Editor', href: route('admin.games.edit', props.game.id), active: false },
    { label: 'Sledování', href: route('admin.games.tracking', props.game.id), active: true },
]);

// 'live' = Reverb connected, 'connecting' = Reverb (re)connecting,
// 'polling' = polling without Reverb OK, 'error' = socket down or last poll failed.
const connectionState = computed(() => {
    if (usingEcho.value) {
        if (socketState.value === 'connected') return 'live';
        if (socketState.value === 'connecting' || socketState.value === 'initialized') return 'connecting';

        return 'error';
    }

    return pollFailed.value ? 'error' : 'polling';
});

const selectedLivePlayer = computed(() => livePlayers.value.find((p) => p.id === selectedPlayerId.value) ?? null);

// livePlayers keeps hidden players too (so unhiding is instant); the list, the
// map and the stats only ever see the visible ones.
const playerGroups = computed(() => splitPlayers(livePlayers.value));
const visiblePlayers = computed(() => playerGroups.value.visible);
const hiddenPlayers = computed(() => playerGroups.value.hidden);

const sortedPlayers = computed(() => sortPlayers(visiblePlayers.value, now.value));

const stats = computed(() => {
    const players = visiblePlayers.value;

    return [
        { label: 'Hráči', value: players.length },
        { label: 'Právě hrají', value: players.filter((p) => !p.finished_at && !isStale(p, now.value)).length },
        { label: 'Dokončili', value: players.filter((p) => p.finished_at).length },
    ];
});

// --- Hiding & deleting players ----------------------------------------------

const playerLabel = (player) => `${avatarEmoji(player.avatar)} ${player.nickname}`;

const replacePlayer = (updated) => {
    mutationVersion++;
    livePlayers.value = livePlayers.value.map((p) => (p.id === updated.id ? updated : p));
};

const removePlayers = (ids) => {
    mutationVersion++;
    ids.forEach((id) => deletedIds.add(id));
    const gone = new Set(ids);
    livePlayers.value = livePlayers.value.filter((p) => !gone.has(p.id));
};

// Close the detail once its player is hidden or deleted — by this page or by
// the server state arriving over a poll.
watch(selectedLivePlayer, (player) => {
    if (selectedPlayerId.value !== null && (!player || player.hidden)) selectedPlayerId.value = null;
});

// --- Walked path of the selected player -------------------------------------

// Loaded when a player is selected, then grown with only the newer points
// (`after`) whenever their position changes — per poll or per live ping.
const selectedPath = ref([]);
let pathPlayerId = null;
let pathLastId = 0;
// Bumped on every player switch, so a late response never lands in a newer
// (possibly the same player's, restarted) path.
let pathGeneration = 0;
// Generation whose request is in flight (null = none).
let pathInFlight = null;

const loadPath = async () => {
    const playerId = selectedPlayerId.value;

    if (playerId !== pathPlayerId) {
        pathPlayerId = playerId;
        pathLastId = 0;
        pathGeneration++;
        selectedPath.value = [];
    }

    // A request for this selection is already in flight; the next position
    // change picks up anything newer. A new selection never waits.
    if (playerId === null || pathInFlight === pathGeneration) return;

    const generation = pathGeneration;
    pathInFlight = generation;

    try {
        const { data } = await window.axios.get(route('admin.games.players.path', [props.game.id, playerId]), {
            params: { after: pathLastId },
        });

        // The dozor may have switched players meanwhile.
        if (generation !== pathGeneration) return;

        if (data.points.length) selectedPath.value = [...selectedPath.value, ...data.points];
        pathLastId = data.last_id;
    } catch (error) {
        // The line is a nice-to-have; the next position change retries.
        console.error('Failed to load the player path', error);
    } finally {
        if (pathInFlight === generation) pathInFlight = null;
    }
};

watch(selectedPlayerId, loadPath);
watch(() => selectedLivePlayer.value?.position, loadPath);

// Ids of players with a single-player hide/unhide request in flight.
const busyIds = ref(new Set());

const setVisibility = async (player, hide) => {
    if (busyIds.value.has(player.id)) return;
    busyIds.value.add(player.id);

    try {
        const routeName = hide ? 'admin.games.players.hide' : 'admin.games.players.unhide';
        const response = await window.axios.patch(route(routeName, [props.game.id, player.id]));
        replacePlayer(response.data.player);
    } catch (error) {
        console.error('Failed to change player visibility', error);
        pushToast(
            hide
                ? `Hráče ${playerLabel(player)} se nepodařilo skrýt.`
                : `Hráče ${playerLabel(player)} se nepodařilo zobrazit.`,
            'error',
        );
    } finally {
        busyIds.value.delete(player.id);
    }
};

const hidePlayer = (player) => setVisibility(player, true);
const unhidePlayer = (player) => setVisibility(player, false);

// Selection mode (multi-select on the list).
const selecting = ref(false);
const checkedIds = ref(new Set());

const toggleSelecting = () => {
    selecting.value = !selecting.value;
    checkedIds.value = new Set();
};

const toggleChecked = (id) => {
    if (checkedIds.value.has(id)) checkedIds.value.delete(id);
    else checkedIds.value.add(id);
};

// Only players still in the list count (others may have vanished meanwhile).
const checkedPlayerIds = computed(() => visiblePlayers.value.filter((p) => checkedIds.value.has(p.id)).map((p) => p.id));

// Leave selection mode once there is nothing left to select.
watch(
    () => visiblePlayers.value.length,
    (count) => {
        if (count === 0 && selecting.value) toggleSelecting();
    },
);

const bulkBusy = ref(false);

const runBulk = async (action, ids) => {
    await window.axios.post(route('admin.games.players.bulk', props.game.id), { action, ids });

    if (action === 'delete') {
        removePlayers(ids);

        return;
    }

    mutationVersion++;
    const affected = new Set(ids);
    const hidden = action === 'hide';
    livePlayers.value = livePlayers.value.map((p) => (affected.has(p.id) ? { ...p, hidden } : p));
};

const hideChecked = async () => {
    const ids = checkedPlayerIds.value;
    if (!ids.length || bulkBusy.value) return;

    bulkBusy.value = true;
    try {
        await runBulk('hide', ids);
        selecting.value = false;
        checkedIds.value = new Set();
    } catch (error) {
        console.error('Failed to hide players', error);
        pushToast('Vybrané hráče se nepodařilo skrýt.', 'error');
    } finally {
        bulkBusy.value = false;
    }
};

// "Hide inactive" is reversible, so it runs without a confirmation.
const inactivePlayerIds = computed(() => inactiveIds(livePlayers.value, now.value));
const hidingInactive = ref(false);

const hideInactive = async () => {
    const ids = inactivePlayerIds.value;
    if (!ids.length || hidingInactive.value) return;

    hidingInactive.value = true;
    try {
        await runBulk('hide', ids);
    } catch (error) {
        console.error('Failed to hide inactive players', error);
        pushToast('Neaktivní hráče se nepodařilo skrýt.', 'error');
    } finally {
        hidingInactive.value = false;
    }
};

// Deleting (single or the checked ones) always goes through the dialog.
// { kind: 'single', player } | { kind: 'bulk', ids }
const pendingDelete = ref(null);
const deleting = ref(false);
const deleteError = ref(null);

const askDeletePlayer = (player) => {
    deleteError.value = null;
    pendingDelete.value = { kind: 'single', player };
};

const askDeleteChecked = () => {
    const ids = checkedPlayerIds.value;
    if (!ids.length) return;

    deleteError.value = null;
    pendingDelete.value = { kind: 'bulk', ids };
};

const cancelDelete = () => {
    if (deleting.value) return;
    pendingDelete.value = null;
    deleteError.value = null;
};

const deleteMessage = computed(() => {
    const pending = pendingDelete.value;
    if (!pending) return '';
    if (pending.kind === 'single') return `Smazat hráče \u201E${playerLabel(pending.player)}\u201C?`;

    return `Smazat ${pluralize(pending.ids.length, 'hráče', 'hráče', 'hráčů')}?`;
});

const deleteWarning = computed(() =>
    pendingDelete.value?.kind === 'bulk' && pendingDelete.value.ids.length > 1
        ? 'Smaže se i jejich průběh a fotky. Tato akce je nevratná.'
        : 'Smaže se i jeho průběh a fotky. Tato akce je nevratná.',
);

const confirmDelete = async () => {
    const pending = pendingDelete.value;
    if (!pending || deleting.value) return;

    deleting.value = true;
    deleteError.value = null;
    try {
        if (pending.kind === 'single') {
            await window.axios.delete(route('admin.games.players.destroy', [props.game.id, pending.player.id]));
            removePlayers([pending.player.id]);
        } else {
            await runBulk('delete', pending.ids);
            selecting.value = false;
            checkedIds.value = new Set();
        }
        pendingDelete.value = null;
    } catch (error) {
        console.error('Failed to delete players', error);
        deleteError.value = 'Smazání se nepodařilo. Zkus to znovu.';
    } finally {
        deleting.value = false;
    }
};

const showHidden = ref(false);

const mapRef = ref(null);
const panelEl = ref(null);

// Every panel switch (list ↔ detail) starts at the top.
watch(selectedPlayerId, async () => {
    await nextTick();
    if (panelEl.value) panelEl.value.scrollTop = 0;
});

onBeforeUnmount(() => {
    clearInterval(nowTimer);
    stopPolling();
    if (socketConnection) socketConnection.unbind('state_changed', onSocketStateChange);
    if (echo) echo.leave(channelName);
});
</script>

<template>
    <Head :title="`Sledování – ${game.name}`" />

    <AuthenticatedLayout full-bleed>
        <template #breadcrumb>
            <Link :href="route('admin.games.index')" class="hover:text-teal-700">Moje hry</Link>
            <span class="text-slate-300" aria-hidden="true">/</span>
            <span class="min-w-0 truncate font-medium text-slate-800">{{ game.name }}</span>
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
                        :busy="busyIds.has(selectedPlayerId)"
                        @hide="hidePlayer"
                        @unhide="unhidePlayer"
                        @delete="askDeletePlayer"
                    />
                </div>

                <div v-else class="space-y-5 p-5" :class="{ 'pb-0': selecting && sortedPlayers.length }">
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
                            <template v-else-if="connectionState === 'connecting'">
                                <span class="h-2.5 w-2.5 rounded-full bg-slate-400" aria-hidden="true" />
                                <span class="text-slate-600">Připojuji…</span>
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
                        <div class="mb-2 flex flex-wrap items-center gap-2 pl-1">
                            <h2 id="players-heading" class="mr-auto text-sm font-semibold text-slate-800">Hráči</h2>
                            <Button
                                v-if="inactivePlayerIds.length && !selecting"
                                size="sm"
                                variant="ghost"
                                :processing="hidingInactive"
                                @click="hideInactive"
                            >Skrýt neaktivní ({{ inactivePlayerIds.length }})</Button>
                            <Button
                                size="sm"
                                variant="secondary"
                                :class="{ '!bg-teal-50 !text-teal-800 !ring-teal-400': selecting }"
                                :aria-pressed="selecting"
                                @click="toggleSelecting"
                            >Vybrat</Button>
                        </div>
                        <ul class="space-y-0.5">
                            <li v-for="player in sortedPlayers" :key="player.id">
                                <PlayerCard
                                    :player="player"
                                    :now="now"
                                    :selected="player.id === selectedPlayerId"
                                    :selection-mode="selecting"
                                    :checked="checkedIds.has(player.id)"
                                    @select="selectedPlayerId = $event"
                                    @toggle="toggleChecked"
                                    @hide="hidePlayer"
                                    @delete="askDeletePlayer"
                                />
                            </li>
                        </ul>
                    </section>

                    <p
                        v-else-if="hiddenPlayers.length"
                        class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500 ring-1 ring-slate-900/5"
                    >
                        Všichni hráči jsou skrytí.
                    </p>

                    <section v-if="hiddenPlayers.length" aria-labelledby="hidden-players-heading">
                        <h2 id="hidden-players-heading">
                            <button
                                type="button"
                                class="flex w-full items-center gap-2 rounded-lg px-1 py-1 text-left text-sm font-semibold text-slate-600 transition hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                                :aria-expanded="showHidden"
                                aria-controls="hidden-players-list"
                                @click="showHidden = !showHidden"
                            >
                                <svg
                                    class="h-4 w-4 shrink-0 transition-transform"
                                    :class="{ 'rotate-90': showHidden }"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                                Skrytí hráči ({{ hiddenPlayers.length }})
                            </button>
                        </h2>
                        <ul v-show="showHidden" id="hidden-players-list" class="mt-1 divide-y divide-slate-100">
                            <li v-for="player in hiddenPlayers" :key="player.id" class="flex items-center gap-3 py-1.5 pl-7">
                                <span class="min-w-0 flex-1 truncate text-sm text-slate-600">{{ playerLabel(player) }}</span>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="shrink-0"
                                    :processing="busyIds.has(player.id)"
                                    @click="unhidePlayer(player)"
                                >Zobrazit</Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="shrink-0 !text-red-600 hover:!bg-red-50"
                                    @click="askDeletePlayer(player)"
                                >Smazat</Button>
                            </li>
                        </ul>
                    </section>

                    <div
                        v-if="selecting && sortedPlayers.length"
                        class="sticky bottom-0 -mx-5 flex flex-wrap items-center gap-2 border-t border-slate-200 bg-white/95 px-5 py-3 backdrop-blur"
                    >
                        <p class="mr-auto text-sm font-semibold text-slate-800" aria-live="polite">Vybráno {{ checkedPlayerIds.length }}</p>
                        <Button
                            size="sm"
                            variant="secondary"
                            :processing="bulkBusy"
                            :disabled="!checkedPlayerIds.length || bulkBusy"
                            @click="hideChecked"
                        >Skrýt</Button>
                        <Button
                            size="sm"
                            variant="danger"
                            :disabled="!checkedPlayerIds.length || bulkBusy"
                            @click="askDeleteChecked"
                        >Smazat</Button>
                        <Button size="sm" variant="ghost" @click="toggleSelecting">Zrušit</Button>
                    </div>

                    <EmptyState
                        v-if="!livePlayers.length"
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
                    :players="visiblePlayers"
                    :now="now"
                    :path="selectedPath"
                    :path-color="selectedLivePlayer?.color"
                    :path-key="selectedPlayerId"
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
                    v-if="locations.length || visiblePlayers.length"
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
                class="rounded-xl border-l-4 bg-white px-4 py-3 text-sm font-medium text-slate-800 shadow-lg ring-1 ring-slate-900/10"
                :class="toast.tone === 'error' ? 'border-red-500' : 'border-teal-500'"
                :role="toast.tone === 'error' ? 'alert' : undefined"
            >
                {{ toast.message }}
            </div>
        </div>

        <ConfirmDialog
            :show="pendingDelete !== null"
            :message="deleteMessage"
            :warning="deleteWarning"
            :error="deleteError"
            :processing="deleting"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </AuthenticatedLayout>
</template>
