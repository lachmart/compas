<script setup>
import TaskTypeIcon from '@/Components/Admin/TaskTypeIcon.vue';
import { TASK_TYPE_LABELS } from '@/lib/taskTypes';

const model = defineModel({ type: String, required: true });

const DESCRIPTIONS = {
    arrival_only: 'Stačí dojít na místo',
    pick_image: 'Hráč vybere správný z 3–6 obrázků',
    photo_of_something: 'Hráč vyfotí, co ukazuje obrázek',
    selfie_doing_something: 'Selfie podle předlohy',
    password: 'Hráč musí napsat správné heslo',
};

const options = Object.entries(TASK_TYPE_LABELS).map(([value, label]) => ({
    value,
    label,
    description: DESCRIPTIONS[value],
}));
</script>

<template>
    <div role="radiogroup" aria-label="Typ úkolu" class="grid grid-cols-2 gap-2.5">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="model === option.value"
            class="flex flex-col items-start gap-1.5 rounded-xl p-3 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2"
            :class="
                model === option.value
                    ? 'bg-teal-50 ring-2 ring-teal-500'
                    : 'bg-white ring-1 ring-slate-200 hover:bg-slate-50 hover:ring-slate-300'
            "
            @click="model = option.value"
        >
            <span
                class="flex h-8 w-8 items-center justify-center rounded-lg"
                :class="model === option.value ? 'bg-teal-600 text-white' : 'bg-slate-100 text-slate-600'"
            >
                <TaskTypeIcon :type="option.value" class="h-5 w-5" />
            </span>
            <span class="text-sm font-semibold leading-tight text-slate-900">{{ option.label }}</span>
            <span class="text-xs leading-snug text-slate-500">{{ option.description }}</span>
        </button>
    </div>
</template>
