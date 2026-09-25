<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import GameHeader from '@/Components/Admin/GameHeader.vue';
import GameMap from '@/Components/Admin/GameMap.vue';
import GameSettings from '@/Components/Admin/GameSettings.vue';
import Modal from '@/Components/Modal.vue';
import Button from '@/Components/UI/Button.vue';
import LocationForm from '@/Components/Admin/LocationForm.vue';
import LocationList from '@/Components/Admin/LocationList.vue';
import SharePanel from '@/Components/Admin/SharePanel.vue';
import SegmentedTabs from '@/Components/UI/SegmentedTabs.vue';
import { addState, editState, hasUnsavedChanges, listState, mapModeFor, reconcileWithLocations } from '@/lib/editorPanel';
import { locationLabel } from '@/lib/taskTypes';

const props = defineProps({
    game: Object,
    locations: Array,
    play_url: String,
    players_count: { type: Number, default: 0 },
});

// { panel: 'list' | 'edit' | 'add', selectedId, draft } — see lib/editorPanel.js.
const state = ref(listState());

const mapRef = ref(null);
const panelEl = ref(null);
const formRef = ref(null);

const mapMode = computed(() => mapModeFor(state.value.panel));
const formOpen = computed(() => state.value.panel !== 'list');
const isPublished = computed(() => props.game.status === 'published');

const selectedIndex = computed(() => props.locations.findIndex((l) => l.id === state.value.selectedId));
const selectedLocation = computed(() => props.locations[selectedIndex.value] ?? null);

const tabs = computed(() => [
    { label: 'Editor', href: route('admin.games.edit', props.game.id), active: true },
    { label: 'Sledování', href: route('admin.games.tracking', props.game.id), active: false },
]);

const mapHint = computed(() => {
    if (state.value.panel === 'add') {
        return state.value.draft?.lat == null ? 'Klikni do mapy, kam stanoviště umístit' : 'Přetáhni značku nebo klikni jinam';
    }
    if (state.value.panel === 'edit') return 'Přetáhni značku nebo klikni do mapy';

    return null;
});

// Leaving the open form (another station, or a new one) would drop its
// unsaved changes: ask first (save / discard / stay). `action` runs once
// the way is clear.
const pendingAction = ref(null);

const leaveFormThen = (action) => {
    if (hasUnsavedChanges(state.value, selectedLocation.value, Boolean(formRef.value?.isDirty()))) {
        pendingAction.value = action;
        return;
    }

    action();
};

const openLocation = (id) => {
    if (id === state.value.selectedId) return;

    leaveFormThen(() => switchTo(id));
};

const switchTo = (id) => {
    const location = props.locations.find((l) => l.id === id);
    if (!location) return;

    state.value = editState(location);
    mapRef.value?.flyTo(location.lat, location.lng);
};

const startAdding = () => {
    // Already adding: keep the new station being placed.
    if (state.value.panel === 'add') return;

    leaveFormThen(() => {
        state.value = addState();
    });
};

const backToList = () => {
    state.value = listState();
};

const saveAndContinue = () => {
    const action = pendingAction.value;
    pendingAction.value = null;
    // On validation errors the form stays open and shows them.
    formRef.value?.save(action);
};

const discardAndContinue = () => {
    const action = pendingAction.value;
    pendingAction.value = null;
    action();
};

// GameMap only reports { lat, lng }; keep the radius.
const onMapDraft = (position) => {
    if (!state.value.draft) return;
    state.value = { ...state.value, draft: { ...state.value.draft, ...position } };
};

const onFormDraft = (draft) => {
    state.value = { ...state.value, draft };
};

watch(() => props.locations, (locations) => {
    state.value = reconcileWithLocations(state.value, locations);
});

// Every panel switch starts at the top (the panel scrolls independently of the map).
watch(() => [state.value.panel, state.value.selectedId], async () => {
    await nextTick();
    if (panelEl.value) panelEl.value.scrollTop = 0;
});

// Location delete (from the open form).
const pendingDelete = ref(null); // { location, position }
const deleting = ref(false);

const deleteWarning = computed(() =>
    isPublished.value ? 'Hráči, kteří už toto stanoviště plní, přijdou o svůj průběh na něm.' : null,
);

const askDelete = () => {
    if (!selectedLocation.value) return;
    pendingDelete.value = { location: selectedLocation.value, position: selectedIndex.value + 1 };
};

const confirmDelete = () => {
    const { location } = pendingDelete.value;

    deleting.value = true;
    router.delete(route('admin.games.locations.destroy', [props.game.id, location.id]), {
        preserveScroll: true,
        onSuccess: () => {
            pendingDelete.value = null;
            backToList();
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
};
</script>

<template>
    <Head :title="game.name" />

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
                <!-- The list stays mounted (v-show) so GameSettings keeps its open/unsaved state. -->
                <div v-show="state.panel === 'list'" class="space-y-6 p-5">
                    <GameHeader :game="game" :locations-count="locations.length" :players-count="players_count" />
                    <SharePanel :play-url="play_url" :slug="game.slug" :published="isPublished" />
                    <LocationList
                        :game="game"
                        :locations="locations"
                        :selected-id="state.selectedId"
                        :locked="formOpen"
                        @select="openLocation"
                        @add="startAdding"
                    />
                    <GameSettings :game="game" :players-count="players_count" />
                </div>

                <LocationForm
                    v-if="formOpen"
                    ref="formRef"
                    :key="state.panel === 'edit' ? `edit:${state.selectedId}` : 'add'"
                    :game="game"
                    :location="selectedLocation"
                    :position="selectedIndex + 1"
                    :draft="state.draft"
                    @update:draft="onFormDraft"
                    @located="({ lat, lng }) => mapRef?.flyTo(lat, lng)"
                    @saved="backToList"
                    @cancel="backToList"
                    @delete="askDelete"
                />
            </aside>

            <div class="relative isolate order-first h-[45vh] shrink-0 border-b border-slate-200 lg:order-none lg:h-auto lg:flex-1 lg:border-b-0">
                <GameMap
                    ref="mapRef"
                    :locations="locations"
                    :selected-id="state.selectedId"
                    :draft="state.draft"
                    :mode="mapMode"
                    @select="openLocation"
                    @update:draft="onMapDraft"
                />

                <div class="pointer-events-none absolute inset-x-0 top-3 z-[1000] flex justify-center px-14">
                    <p
                        v-if="mapHint"
                        class="flex items-center gap-2 rounded-full bg-slate-900/85 px-4 py-2 text-center text-sm font-medium text-white shadow-lg backdrop-blur"
                    >
                        <span class="h-2 w-2 shrink-0 rounded-full bg-orange-400" aria-hidden="true" />
                        {{ mapHint }}
                    </p>
                </div>

                <!-- Always at hand: add a station from any panel. -->
                <button
                    v-if="state.panel !== 'add'"
                    type="button"
                    class="absolute bottom-4 left-3 z-[1000] inline-flex items-center gap-1.5 rounded-full bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg ring-2 ring-white transition hover:bg-teal-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-300"
                    @click="startAdding"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
                    </svg>
                    Přidat stanoviště
                </button>

                <button
                    v-if="locations.length"
                    type="button"
                    class="absolute right-3 top-3 z-[1000] flex h-[34px] w-[34px] items-center justify-center rounded-lg bg-white text-slate-600 shadow ring-1 ring-slate-900/15 transition hover:bg-slate-50 hover:text-teal-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                    title="Zobrazit všechna stanoviště"
                    aria-label="Zobrazit všechna stanoviště"
                    @click="mapRef?.fitAll()"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M3.28 2.22a.75.75 0 0 0-1.06 1.06L5.44 6.5H2.75a.75.75 0 0 0 0 1.5h4.5A.75.75 0 0 0 8 7.25v-4.5a.75.75 0 0 0-1.5 0v2.69L3.28 2.22ZM13.5 2.75a.75.75 0 0 0-1.5 0v4.5c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-2.69l3.22-3.22a.75.75 0 0 0-1.06-1.06L13.5 5.44V2.75ZM3.28 17.78l3.22-3.22v2.69a.75.75 0 0 0 1.5 0v-4.5a.75.75 0 0 0-.75-.75h-4.5a.75.75 0 0 0 0 1.5h2.69l-3.22 3.22a.75.75 0 1 0 1.06 1.06ZM13.5 14.56l3.22 3.22a.75.75 0 1 0 1.06-1.06l-3.22-3.22h2.69a.75.75 0 0 0 0-1.5h-4.5a.75.75 0 0 0-.75.75v4.5a.75.75 0 0 0 1.5 0v-2.69Z" />
                    </svg>
                </button>
            </div>
        </div>

        <Modal :show="pendingAction !== null" max-width="md" @close="pendingAction = null">
            <div class="space-y-4 p-6">
                <p class="font-semibold text-slate-900">Stanoviště má neuložené změny</p>
                <p class="text-sm text-slate-600">Chceš je uložit, nebo zahodit, než budeš pokračovat?</p>
                <div class="flex flex-wrap justify-end gap-3">
                    <Button variant="ghost" type="button" @click="pendingAction = null">Zůstat</Button>
                    <Button variant="secondary" type="button" class="!text-red-600" @click="discardAndContinue">Zahodit změny</Button>
                    <Button type="button" @click="saveAndContinue">Uložit a pokračovat</Button>
                </div>
            </div>
        </Modal>

        <ConfirmDialog
            :show="pendingDelete !== null"
            :message="pendingDelete ? `Smazat stanoviště ${locationLabel(pendingDelete.location, pendingDelete.position)}? Tato akce je nevratná.` : ''"
            :warning="deleteWarning"
            :processing="deleting"
            @confirm="confirmDelete"
            @cancel="pendingDelete = null"
        />
    </AuthenticatedLayout>
</template>
