<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import TaskContentFields from '@/Components/Admin/TaskContentFields.vue';
import TaskTypePicker from '@/Components/Admin/TaskTypePicker.vue';
import Button from '@/Components/UI/Button.vue';
import FormField from '@/Components/UI/FormField.vue';
import { radiusSliderMax } from '@/lib/editorPanel';
import { locationLabel } from '@/lib/taskTypes';

const props = defineProps({
    game: { type: Object, required: true },
    // null = new location.
    location: { type: Object, default: null },
    // 1-based place of `location` in the game (for the heading).
    position: { type: Number, default: null },
    // { lat, lng, arrival_radius_m } — shared with the map (v-model:draft).
    draft: { type: Object, required: true },
});
const emit = defineEmits(['update:draft', 'located', 'saved', 'cancel', 'delete']);

// Position and radius live in `draft` (the map edits them too); the rest is here.
const form = useForm({
    name: props.location?.name ?? '',
    task_type: props.location?.task_type ?? 'arrival_only',
    existing_images: (props.location?.images ?? []).map((image) => image.path),
    images: [],
    correct_index: props.location?.correct_index ?? null,
    reference_image: null,
});

// Lets the editor avoid discarding edits when another station is clicked on the map.
defineExpose({ isDirty: () => form.isDirty });

const heading = computed(() =>
    props.location ? `Upravuješ: ${locationLabel(props.location, props.position)}` : 'Nové stanoviště',
);

const hasPosition = computed(() => props.draft.lat != null && props.draft.lng != null);

const radius = computed({
    get: () => props.draft.arrival_radius_m,
    set: (value) => emit('update:draft', { ...props.draft, arrival_radius_m: Number(value) }),
});

const locating = ref(false);
const locateError = ref(null);
let unmounted = false;
onBeforeUnmount(() => { unmounted = true; });

const useMyLocation = () => {
    if (!navigator.geolocation) {
        locateError.value = 'Tento prohlížeč polohu nezjistí.';
        return;
    }

    locating.value = true;
    locateError.value = null;
    navigator.geolocation.getCurrentPosition(
        ({ coords }) => {
            if (unmounted) return;
            locating.value = false;
            emit('update:draft', { ...props.draft, lat: coords.latitude, lng: coords.longitude });
            emit('located', { lat: coords.latitude, lng: coords.longitude });
        },
        () => {
            if (unmounted) return;
            locating.value = false;
            locateError.value = 'Polohu se nepodařilo zjistit. Povol ji v prohlížeči, nebo klikni do mapy.';
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
    );
};

const submit = () => {
    const options = { forceFormData: true, preserveScroll: true, onSuccess: () => emit('saved') };
    const withDraft = (data) => ({
        ...data,
        lat: props.draft.lat,
        lng: props.draft.lng,
        arrival_radius_m: props.draft.arrival_radius_m,
    });

    if (props.location) {
        form.transform((data) => ({ ...withDraft(data), _method: 'put' })).post(
            route('admin.games.locations.update', [props.game.id, props.location.id]),
            options,
        );
    } else {
        form.transform(withDraft).post(route('admin.games.locations.store', props.game.id), options);
    }
};

// Fixed per form so dragging a legacy radius (> 100 m) down doesn't shrink the scale under the thumb.
const sliderMax = radiusSliderMax(props.draft.arrival_radius_m);

const formatCoord = (value) => Number(value).toFixed(5);

const inputClasses =
    'block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500';
</script>

<template>
    <form class="flex min-h-full flex-col" @submit.prevent="submit">
        <div class="flex-1 space-y-6 p-5">
            <div>
                <button
                    type="button"
                    class="-ml-1 inline-flex items-center gap-1 rounded-lg px-1 py-0.5 text-sm font-medium text-teal-700 hover:text-teal-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                    @click="emit('cancel')"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                    </svg>
                    Zpět na seznam
                </button>
                <h2 class="mt-2 break-words text-lg font-bold text-slate-900">{{ heading }}</h2>
            </div>

            <!-- Position -->
            <div
                class="rounded-xl p-4 ring-1"
                :class="hasPosition ? 'bg-slate-50 ring-slate-900/5' : 'bg-orange-50 ring-orange-200'"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white"
                        :class="hasPosition ? 'bg-teal-600' : 'bg-orange-500'"
                        aria-hidden="true"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="m9.69 18.933.003.001C9.89 19.02 10 19 10 19s.11.02.308-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 0 0 .281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 14.988 17 12.493 17 9A7 7 0 1 0 3 9c0 3.492 1.698 5.988 3.355 7.584a13.731 13.731 0 0 0 2.273 1.765 11.842 11.842 0 0 0 .976.544l.062.029.018.008.006.003ZM10 11.25a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-800">Pozice</p>
                        <p class="text-xs text-slate-600">
                            {{ hasPosition ? 'Klikni do mapy nebo přetáhni značku.' : 'Klikni do mapy, kam stanoviště umístit.' }}
                        </p>
                        <p v-if="hasPosition" class="mt-1 font-mono text-[11px] text-slate-400">
                            {{ formatCoord(draft.lat) }}, {{ formatCoord(draft.lng) }}
                        </p>
                    </div>
                </div>

                <Button size="sm" variant="secondary" class="mt-3" :processing="locating" @click="useMyLocation">
                    <template #icon>
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 1a.75.75 0 0 1 .75.75v1.28a7.003 7.003 0 0 1 6.22 6.22h1.28a.75.75 0 0 1 0 1.5h-1.28a7.003 7.003 0 0 1-6.22 6.22v1.28a.75.75 0 0 1-1.5 0v-1.28a7.003 7.003 0 0 1-6.22-6.22H1.75a.75.75 0 0 1 0-1.5h1.28a7.003 7.003 0 0 1 6.22-6.22V1.75A.75.75 0 0 1 10 1Zm0 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM10 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z" clip-rule="evenodd" />
                        </svg>
                    </template>
                    Použít moji polohu
                </Button>
                <p v-if="locateError" class="mt-2 text-xs text-amber-700">{{ locateError }}</p>
                <p v-if="form.errors.lat" class="mt-2 text-sm text-red-600">{{ form.errors.lat }}</p>
                <p v-if="form.errors.lng" class="mt-2 text-sm text-red-600">{{ form.errors.lng }}</p>
            </div>

            <FormField
                label="Název"
                for="location-name"
                hint="Nepovinné — dítě ho neuvidí, pomůže jen tobě."
                :error="form.errors.name"
            >
                <input
                    id="location-name"
                    v-model="form.name"
                    type="text"
                    maxlength="100"
                    :placeholder="location ? `Stanoviště ${position}` : 'např. Velký dub'"
                    :class="inputClasses"
                />
            </FormField>

            <div>
                <p class="mb-2 text-sm font-medium text-slate-700">Typ úkolu</p>
                <TaskTypePicker v-model="form.task_type" />
                <p v-if="form.errors.task_type" class="mt-1 text-sm text-red-600">{{ form.errors.task_type }}</p>
            </div>

            <div>
                <div class="flex items-baseline justify-between">
                    <label for="location-radius" class="text-sm font-medium text-slate-700">Poloměr příchodu</label>
                    <span class="text-sm font-semibold tabular-nums text-teal-800">{{ radius }} m</span>
                </div>
                <p class="mt-0.5 text-xs text-slate-500">Jak blízko musí dítě dojít, aby stanoviště našlo.</p>
                <input
                    id="location-radius"
                    v-model.number="radius"
                    type="range"
                    min="5"
                    :max="sliderMax"
                    step="1"
                    class="mt-2 w-full accent-teal-600"
                />
                <div class="flex justify-between text-[11px] text-slate-400" aria-hidden="true">
                    <span>5 m</span>
                    <span>{{ sliderMax }} m</span>
                </div>
                <p v-if="form.errors.arrival_radius_m" class="mt-1 text-sm text-red-600">{{ form.errors.arrival_radius_m }}</p>
            </div>

            <TaskContentFields :task-type="form.task_type" :form="form" :location="location" />
        </div>

        <div class="sticky bottom-0 flex items-center gap-2 border-t border-slate-200 bg-white p-4">
            <Button type="submit" :processing="form.processing">Uložit stanoviště</Button>
            <Button variant="ghost" @click="emit('cancel')">Zrušit</Button>
            <Button
                v-if="location"
                variant="ghost"
                class="ml-auto !text-red-600 hover:!bg-red-50"
                @click="emit('delete')"
            >
                Smazat
            </Button>
        </div>
    </form>
</template>
