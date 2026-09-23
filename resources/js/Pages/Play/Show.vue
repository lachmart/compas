<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import CompassScreen from '@/Components/Play/CompassScreen.vue';
import MapScreen from '@/Components/Play/MapScreen.vue';
import PlayerHud from '@/Components/Play/PlayerHud.vue';
import PickImageTask from '@/Components/Play/PickImageTask.vue';
import PhotoTask from '@/Components/Play/PhotoTask.vue';
import RewardOverlay from '@/Components/Play/RewardOverlay.vue';
import FinishScreen from '@/Components/Play/FinishScreen.vue';
import { usePositionPings } from '@/lib/usePositionPings';

const props = defineProps({
    game: Object,
    locations: Array,
    player: Object,
});

const viewMode = ref('compass');
const currentIndex = ref(props.player.current_location_index);
const finished = ref(props.player.finished_at !== null);
const activeTask = ref(null); // { task_type, images, reference_image } once arrived
const showingReward = ref(false);

// Pings run on every screen (map, tasks, reward) from the start, not just
// while the compass is mounted, and stop once the game is finished. The
// permission prompts were already started from the join screen's tap.
const pingsEnabled = computed(() => !finished.value);
usePositionPings(props.game.slug, pingsEnabled);

const currentLocation = computed(() => props.locations[currentIndex.value] ?? null);

const onArrived = (data) => {
    if (data.player.finished_at) {
        finished.value = true;

        return;
    }

    if (data.attempt_status === 'completed') {
        currentIndex.value = data.player.current_location_index;
        showingReward.value = true;

        return;
    }

    activeTask.value = data;
};

const onTaskCompleted = (playerState) => {
    activeTask.value = null;

    if (playerState.finished_at) {
        finished.value = true;

        return;
    }

    currentIndex.value = playerState.current_location_index;
    showingReward.value = true;
};

const onRewardDone = () => {
    showingReward.value = false;
};

const toggleView = () => {
    viewMode.value = viewMode.value === 'compass' ? 'map' : 'compass';
};
</script>

<template>
    <Head :title="game.name" />

    <FinishScreen v-if="finished" :avatar="player.avatar" :stars="locations.length" />

    <!-- No current location to play (e.g. a published game with zero locations,
         or locations removed mid-session so the player's index now overshoots).
         Reuse FinishScreen as a neutral terminal state rather than crashing on
         a null location prop. -->
    <FinishScreen v-else-if="!currentLocation" :avatar="player.avatar" :stars="locations.length" />

    <!-- Every in-game screen shares one frame so the HUD (animal + stars)
         stays on top of the compass, the map, tasks and the reward. -->
    <div v-else class="relative h-dvh overflow-hidden">
        <RewardOverlay v-if="showingReward" @done="onRewardDone" />

        <PickImageTask
            v-else-if="activeTask && activeTask.task_type === 'pick_image'"
            :images="activeTask.images"
            :game-slug="game.slug"
            :location-id="currentLocation.id"
            @completed="onTaskCompleted"
        />

        <PhotoTask
            v-else-if="activeTask && ['photo_of_something', 'selfie_doing_something'].includes(activeTask.task_type)"
            :reference-image="activeTask.reference_image"
            :task-type="activeTask.task_type"
            :game-slug="game.slug"
            :location-id="currentLocation.id"
            @completed="onTaskCompleted"
        />

        <template v-else>
            <CompassScreen
                v-if="viewMode === 'compass'"
                :location="currentLocation"
                :game-slug="game.slug"
                @arrived="onArrived"
            />
            <MapScreen
                v-else
                :locations="locations"
                :current-location-id="currentLocation.id"
            />

            <button
                type="button"
                @click="toggleView"
                :aria-label="viewMode === 'compass' ? 'Mapa' : 'Kompas'"
                class="absolute bottom-[max(1.5rem,env(safe-area-inset-bottom))] right-5 z-[1000] flex h-16 w-16 items-center justify-center rounded-full bg-white text-teal-700 shadow-xl ring-4 ring-white/60 active:scale-95 motion-safe:transition-transform"
            >
                <!-- Folded map -->
                <svg
                    v-if="viewMode === 'compass'"
                    viewBox="0 0 24 24"
                    class="h-9 w-9"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M3 6.5 9 4l6 2.5L21 4v13.5L15 20l-6-2.5L3 20Z" class="fill-teal-100" />
                    <path d="M9 4v13.5M15 6.5V20" />
                    <circle cx="17.5" cy="10" r="1.6" class="fill-orange-500 stroke-orange-500" />
                </svg>
                <!-- Compass -->
                <svg
                    v-else
                    viewBox="0 0 24 24"
                    class="h-9 w-9"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <circle cx="12" cy="12" r="10" class="fill-teal-100" />
                    <path d="M15.5 8.5 13.4 13.4 10.6 10.6Z" class="fill-orange-500 stroke-orange-500" />
                    <path d="M15.5 8.5 13.4 13.4 8.5 15.5 10.6 10.6Z" />
                </svg>
            </button>
        </template>

        <PlayerHud
            :avatar="player.avatar"
            :total="locations.length"
            :completed="currentIndex"
            :current="currentIndex"
        />
    </div>
</template>
