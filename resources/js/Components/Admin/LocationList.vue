<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import LocationForm from '@/Components/Admin/LocationForm.vue';

const props = defineProps({
    game: Object,
    locations: Array,
});

const editingLocation = ref(null);
const isAdding = ref(false);
const draggedId = ref(null);

const remove = (location) => {
    router.delete(route('admin.games.locations.destroy', [props.game.id, location.id]));
};

const onDragStart = (location) => { draggedId.value = location.id; };
const onDrop = (targetLocation) => {
    const ids = props.locations.map((l) => l.id);
    const from = ids.indexOf(draggedId.value);
    const to = ids.indexOf(targetLocation.id);
    ids.splice(to, 0, ids.splice(from, 1)[0]);

    router.patch(route('admin.games.locations.reorder', props.game.id), { order: ids });
};

const closeForm = () => {
    editingLocation.value = null;
    isAdding.value = false;
};

const editLocation = (location) => {
    isAdding.value = false;
    editingLocation.value = location;
};

const startAdding = () => {
    editingLocation.value = null;
    isAdding.value = true;
};
</script>

<template>
    <div class="space-y-4">
        <ul class="divide-y rounded border bg-white">
            <li
                v-for="location in locations"
                :key="location.id"
                draggable="true"
                @dragstart="onDragStart(location)"
                @dragover.prevent
                @drop="onDrop(location)"
                class="flex items-center justify-between p-3"
            >
                <span>#{{ location.order }} — {{ location.task_type }}</span>
                <span class="space-x-2">
                    <button type="button" class="text-indigo-600" @click="editLocation(location)">Upravit</button>
                    <button type="button" class="text-red-600" @click="remove(location)">Smazat</button>
                </span>
            </li>
            <li v-if="locations.length === 0" class="p-3 text-gray-500">Zatím žádná stanoviště.</li>
        </ul>

        <button v-if="!isAdding && !editingLocation" type="button" class="rounded bg-indigo-600 px-4 py-2 text-white" @click="startAdding">
            Přidat stanoviště
        </button>

        <LocationForm v-if="isAdding" :game="game" @saved="closeForm" @cancel="closeForm" />
        <LocationForm
            v-if="editingLocation"
            :key="editingLocation.id"
            :game="game"
            :location="editingLocation"
            @saved="closeForm"
            @cancel="closeForm"
        />
    </div>
</template>
