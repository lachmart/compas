<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import LocationForm from '@/Components/Admin/LocationForm.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import { locationLabel, taskTypeLabel } from '@/lib/taskTypes';

const props = defineProps({
    game: Object,
    locations: Array,
});

const editingId = ref(null);
const isAdding = ref(false);
const draggedId = ref(null);
const pendingDelete = ref(null); // { location, position }

const formOpen = computed(() => editingId.value !== null || isAdding.value);

const deleteWarning = computed(() =>
    props.game.status === 'published'
        ? 'Hráči, kteří už toto stanoviště plní, přijdou o svůj průběh na něm.'
        : null,
);

const onDragStart = (location) => { draggedId.value = location.id; };
const onDrop = (targetLocation) => {
    const ids = props.locations.map((l) => l.id);
    const from = ids.indexOf(draggedId.value);
    const to = ids.indexOf(targetLocation.id);
    ids.splice(to, 0, ids.splice(from, 1)[0]);

    router.patch(route('admin.games.locations.reorder', props.game.id), { order: ids }, { preserveScroll: true });
};

const closeForm = () => {
    editingId.value = null;
    isAdding.value = false;
};

const editLocation = (location) => {
    isAdding.value = false;
    editingId.value = location.id;
};

const startAdding = () => {
    editingId.value = null;
    isAdding.value = true;
};

const confirmDelete = () => {
    const { location } = pendingDelete.value;
    if (editingId.value === location.id) closeForm();

    router.delete(route('admin.games.locations.destroy', [props.game.id, location.id]), {
        preserveScroll: true,
        onFinish: () => {
            pendingDelete.value = null;
        },
    });
};
</script>

<template>
    <div class="space-y-4">
        <ul class="divide-y rounded border bg-white">
            <li
                v-for="(location, index) in locations"
                :key="location.id"
                :draggable="!formOpen"
                @dragstart="onDragStart(location)"
                @dragover.prevent
                @drop="onDrop(location)"
            >
                <div
                    class="flex items-center justify-between gap-4 p-3"
                    :class="editingId === location.id ? 'border-l-4 border-indigo-500 bg-indigo-50' : ''"
                >
                    <span class="font-medium text-gray-900">
                        {{ locationLabel(location, index + 1) }}
                        <span class="font-normal text-gray-500">· {{ taskTypeLabel(location.task_type) }}</span>
                    </span>
                    <div class="flex items-center gap-8">
                        <button
                            v-if="editingId !== location.id"
                            type="button"
                            class="text-indigo-600"
                            @click="editLocation(location)"
                        >Upravit</button>
                        <button
                            type="button"
                            class="text-sm text-red-400 hover:text-red-600"
                            @click="pendingDelete = { location, position: index + 1 }"
                        >Smazat</button>
                    </div>
                </div>

                <div v-if="editingId === location.id" class="border-l-4 border-indigo-500 bg-indigo-50/40 p-3">
                    <LocationForm
                        :key="location.id"
                        :game="game"
                        :location="location"
                        :heading="`Upravuješ: ${locationLabel(location, index + 1)}`"
                        @saved="closeForm"
                        @cancel="closeForm"
                    />
                </div>
            </li>
            <li v-if="locations.length === 0" class="p-3 text-gray-500">Zatím žádná stanoviště.</li>
        </ul>

        <button v-if="!formOpen" type="button" class="rounded bg-indigo-600 px-4 py-2 text-white" @click="startAdding">
            Přidat stanoviště
        </button>

        <LocationForm v-if="isAdding" :game="game" heading="Nové stanoviště" @saved="closeForm" @cancel="closeForm" />

        <ConfirmDialog
            :show="pendingDelete !== null"
            :message="pendingDelete ? `Smazat stanoviště ${locationLabel(pendingDelete.location, pendingDelete.position)}? Tato akce je nevratná.` : ''"
            :warning="deleteWarning"
            @confirm="confirmDelete"
            @cancel="pendingDelete = null"
        />
    </div>
</template>
