<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import TaskTypeIcon from '@/Components/Admin/TaskTypeIcon.vue';
import Button from '@/Components/UI/Button.vue';
import { locationLabel, taskTypeLabel } from '@/lib/taskTypes';

const props = defineProps({
    game: { type: Object, required: true },
    locations: { type: Array, required: true },
    selectedId: { type: Number, default: null },
    // True while a location form is open: reordering is disabled then.
    locked: { type: Boolean, default: false },
});
const emit = defineEmits(['select', 'add']);

const draggedId = ref(null);
const dropTargetId = ref(null);

const onDragStart = (location) => { draggedId.value = location.id; };
const onDragEnd = () => {
    draggedId.value = null;
    dropTargetId.value = null;
};
const onDragOver = (location) => {
    if (draggedId.value !== null) dropTargetId.value = location.id;
};
const onDrop = (targetLocation) => {
    const ids = props.locations.map((l) => l.id);
    const from = ids.indexOf(draggedId.value);
    draggedId.value = null;
    dropTargetId.value = null;

    if (props.locked || from === -1) return;

    const to = ids.indexOf(targetLocation.id);
    if (from === to) return;
    ids.splice(to, 0, ids.splice(from, 1)[0]);

    router.patch(route('admin.games.locations.reorder', props.game.id), { order: ids }, { preserveScroll: true });
};
</script>

<template>
    <section aria-labelledby="locations-heading">
        <div class="mb-2 flex items-baseline justify-between px-1">
            <h2 id="locations-heading" class="text-sm font-semibold text-slate-800">Stanoviště</h2>
            <p v-if="locations.length > 1 && !locked" class="text-xs text-slate-400">Pořadí změníš přetažením</p>
        </div>

        <ol v-if="locations.length" class="space-y-0.5">
            <li
                v-for="(location, index) in locations"
                :key="location.id"
                :draggable="!locked"
                class="group flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 transition"
                :class="[
                    location.id === selectedId ? 'bg-orange-50 ring-1 ring-orange-200' : 'hover:bg-slate-50',
                    draggedId === location.id ? 'opacity-40' : '',
                    dropTargetId === location.id && draggedId !== location.id ? 'ring-2 ring-teal-400' : '',
                ]"
                @click="emit('select', location.id)"
                @dragstart="onDragStart(location)"
                @dragend="onDragEnd"
                @dragover.prevent="onDragOver(location)"
                @drop.prevent="onDrop(location)"
            >
                <span
                    v-if="!locked"
                    class="-ml-1 cursor-grab text-slate-300 transition group-hover:text-slate-500 active:cursor-grabbing"
                    title="Přetáhni pro změnu pořadí"
                    aria-hidden="true"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <circle cx="7" cy="5" r="1.4" /><circle cx="13" cy="5" r="1.4" />
                        <circle cx="7" cy="10" r="1.4" /><circle cx="13" cy="10" r="1.4" />
                        <circle cx="7" cy="15" r="1.4" /><circle cx="13" cy="15" r="1.4" />
                    </svg>
                </span>

                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white shadow-sm ring-2 ring-white"
                    :class="location.id === selectedId ? 'bg-orange-500' : 'bg-teal-600'"
                >
                    {{ index + 1 }}
                </span>

                <button
                    type="button"
                    class="min-w-0 flex-1 text-left focus:outline-none focus-visible:underline"
                    @click.stop="emit('select', location.id)"
                >
                    <span class="block truncate text-sm font-medium text-slate-900">{{ locationLabel(location, index + 1) }}</span>
                    <span class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
                        <TaskTypeIcon :type="location.task_type" class="h-3.5 w-3.5 shrink-0" />
                        {{ taskTypeLabel(location.task_type) }}
                    </span>
                </button>

                <svg class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:text-slate-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                </svg>
            </li>
        </ol>

        <p v-else class="rounded-xl bg-slate-50 px-4 py-5 text-center text-sm text-slate-500">
            Zatím žádná stanoviště. Přidej první a umísti ho kliknutím do mapy.
        </p>

        <Button variant="secondary" block class="mt-3" @click="emit('add')">
            <template #icon>
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 4a1 1 0 0 1 1 1v4h4a1 1 0 1 1 0 2h-4v4a1 1 0 1 1-2 0v-4H5a1 1 0 1 1 0-2h4V5a1 1 0 0 1 1-1Z" />
                </svg>
            </template>
            Přidat stanoviště
        </Button>
    </section>
</template>
