<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import MapPicker from '@/Components/Admin/MapPicker.vue';
import TaskContentFields from '@/Components/Admin/TaskContentFields.vue';

const props = defineProps({
    game: Object,
    location: { type: Object, default: null },
});
const emit = defineEmits(['saved', 'cancel']);

const form = useForm({
    lat: props.location?.lat ?? null,
    lng: props.location?.lng ?? null,
    arrival_radius_m: props.location?.arrival_radius_m ?? 15,
    task_type: props.location?.task_type ?? 'arrival_only',
    images: [],
    correct_index: props.location?.correct_index ?? 0,
    reference_image: null,
});

const position = computed({
    get: () => ({ lat: form.lat, lng: form.lng }),
    set: ({ lat, lng }) => {
        form.lat = lat;
        form.lng = lng;
    },
});

const submit = () => {
    const options = { forceFormData: true, onSuccess: () => emit('saved') };

    if (props.location) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            route('admin.games.locations.update', [props.game.id, props.location.id]),
            options,
        );
    } else {
        form.post(route('admin.games.locations.store', props.game.id), options);
    }
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-4 rounded border p-4">
        <MapPicker v-model="position" />
        <p v-if="form.errors.lat" class="text-sm text-red-600">{{ form.errors.lat }}</p>
        <p v-if="form.errors.lng" class="text-sm text-red-600">{{ form.errors.lng }}</p>

        <div>
            <label class="block text-sm font-medium text-gray-700">Poloměr "dorazil jsem" (m)</label>
            <input v-model.number="form.arrival_radius_m" type="number" min="5" max="200" class="mt-1 block w-32 rounded border-gray-300" />
            <p v-if="form.errors.arrival_radius_m" class="text-sm text-red-600">{{ form.errors.arrival_radius_m }}</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Typ úkolu</label>
            <select v-model="form.task_type" class="mt-1 block w-full rounded border-gray-300">
                <option value="arrival_only">Jen dojít</option>
                <option value="pick_image">Vybrat obrázek</option>
                <option value="photo_of_something">Vyfotit něco</option>
                <option value="selfie_doing_something">Vyfotit se při činnosti</option>
            </select>
            <p v-if="form.errors.task_type" class="text-sm text-red-600">{{ form.errors.task_type }}</p>
        </div>

        <TaskContentFields :task-type="form.task_type" :form="form" />

        <div class="flex gap-2">
            <button type="submit" :disabled="form.processing" class="rounded bg-indigo-600 px-4 py-2 text-white">
                Uložit stanoviště
            </button>
            <button type="button" @click="emit('cancel')" class="rounded bg-gray-200 px-4 py-2">Zrušit</button>
        </div>
    </form>
</template>
