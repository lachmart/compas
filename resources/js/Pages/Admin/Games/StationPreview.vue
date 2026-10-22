<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import InfoCard from '@/Components/Play/InfoCard.vue';
import PasswordTask from '@/Components/Play/PasswordTask.vue';
import PhotoTask from '@/Components/Play/PhotoTask.vue';
import PickImageTask from '@/Components/Play/PickImageTask.vue';
import RewardOverlay from '@/Components/Play/RewardOverlay.vue';
import { isPhotoTask } from '@/lib/taskTypes';

// One station as the player sees it on arrival, played through without
// touching the game: picks are checked here, passwords by the admin route,
// photos never leave the phone.
const props = defineProps({
    game: Object,
    location: Object,
    position: Number,
    total: Number,
    previous_id: { type: Number, default: null },
    next_id: { type: Number, default: null },
});

// The stage right after arriving, like Play/Show: the text board first,
// then the task; "Jen dojít" goes straight to the reward.
const firstStage = () => {
    if (props.location.info) return 'info';

    return props.location.task_type === 'arrival_only' ? 'reward' : 'task';
};

const stage = ref(firstStage()); // 'info' | 'task' | 'reward' | 'done'
// Remounts the task on a restart so its state starts clean.
const run = ref(0);

const restart = () => {
    stage.value = firstStage();
    run.value++;
};

const afterInfo = () => {
    stage.value = props.location.task_type === 'arrival_only' ? 'reward' : 'task';
};

const label = computed(() => props.location.name || `Stanoviště ${props.position}`);

const previewHref = (id) => route('admin.games.locations.preview', [props.game.id, id]);
const editorHref = computed(() => route('admin.games.edit', props.game.id));

const checkPick = async (index) => ({ correct: index === props.location.correct_index, player: null });

const checkPassword = async (answer) => {
    const response = await window.axios.post(
        route('admin.games.locations.preview.password', [props.game.id, props.location.id]),
        { answer },
    );

    return { correct: response.data.correct, player: null };
};

const keepPhoto = async () => ({ player: null });

// A task the editor would not save, shown instead of a broken screen.
const missingMedia = computed(() => {
    if (props.location.task_type === 'pick_image' && !props.location.images?.length) return 'Chybí obrázky na výběr.';
    if (isPhotoTask(props.location.task_type) && !props.location.reference_image) return 'Chybí vzorová fotka.';

    return null;
});
</script>

<template>
    <Head :title="`Náhled: ${label}`" />

    <div class="relative h-dvh overflow-hidden">
        <InfoCard v-if="stage === 'info'" :info="location.info" @continue="afterInfo" />

        <template v-else-if="stage === 'task'">
            <div
                v-if="missingMedia"
                class="flex h-dvh items-center justify-center bg-gradient-to-b from-teal-400 to-teal-700 px-6 pt-28 text-center text-xl font-semibold text-white"
            >
                {{ missingMedia }}
            </div>

            <PasswordTask
                v-else-if="location.task_type === 'password'"
                :key="run"
                :game-slug="game.slug"
                :location-id="location.id"
                :info="location.info"
                :submit="checkPassword"
                @completed="stage = 'reward'"
            />

            <PickImageTask
                v-else-if="location.task_type === 'pick_image'"
                :key="run"
                :images="location.images"
                :game-slug="game.slug"
                :location-id="location.id"
                :submit="checkPick"
                @completed="stage = 'reward'"
            />

            <PhotoTask
                v-else-if="isPhotoTask(location.task_type)"
                :key="run"
                :reference-image="location.reference_image"
                :task-type="location.task_type"
                :game-slug="game.slug"
                :location-id="location.id"
                :submit="keepPhoto"
                @completed="stage = 'reward'"
            />
        </template>

        <RewardOverlay v-else-if="stage === 'reward'" @done="stage = 'done'" />

        <div
            v-else
            class="flex h-dvh flex-col items-center justify-center gap-6 bg-gradient-to-b from-emerald-100 via-teal-50 to-sky-100 px-6 pt-28 text-center"
        >
            <p class="text-2xl font-bold text-teal-900">Hráč jde dál na další stanoviště.</p>
            <div class="flex flex-wrap justify-center gap-3">
                <button
                    type="button"
                    class="rounded-full bg-white px-6 py-3 text-lg font-bold text-teal-800 shadow-xl ring-4 ring-white active:scale-95"
                    @click="restart"
                >
                    Znovu
                </button>
                <Link
                    v-if="next_id"
                    :href="previewHref(next_id)"
                    class="rounded-full bg-orange-500 px-6 py-3 text-lg font-bold text-white shadow-xl ring-4 ring-white active:scale-95"
                >
                    Další stanoviště
                </Link>
            </div>
        </div>

        <!-- In place of the player's HUD (same pt-28 slot): which station
             this is, moving between stations and back to the editor. -->
        <div
            v-if="stage !== 'reward'"
            class="absolute inset-x-0 top-0 z-[1000] flex items-center gap-2 px-4 pt-[max(1rem,env(safe-area-inset-top))]"
        >
            <Link
                :href="editorHref"
                aria-label="Zpět do editoru"
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white text-slate-600 shadow-lg ring-4 ring-white/60 active:scale-95"
            >
                <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18" />
                </svg>
            </Link>

            <div class="flex min-w-0 flex-1 items-center gap-1 rounded-full bg-white/90 py-1 pl-4 pr-1 shadow-lg">
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-orange-600">Náhled hráče · {{ position }}/{{ total }}</p>
                    <p class="truncate text-sm font-semibold text-slate-800">{{ label }}</p>
                </div>

                <button
                    type="button"
                    aria-label="Od začátku"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-teal-700 hover:bg-teal-50"
                    @click="restart"
                >
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 12a8 8 0 1 0 2.3-5.6M4 4v4h4" />
                    </svg>
                </button>
                <Link
                    v-if="previous_id"
                    :href="previewHref(previous_id)"
                    aria-label="Předchozí stanoviště"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-teal-700 hover:bg-teal-50"
                >
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M15 5l-7 7 7 7" />
                    </svg>
                </Link>
                <Link
                    v-if="next_id"
                    :href="previewHref(next_id)"
                    aria-label="Další stanoviště"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-teal-700 hover:bg-teal-50"
                >
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>
        </div>
    </div>
</template>
