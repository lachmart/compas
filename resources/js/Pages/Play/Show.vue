<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import CompassScreen from '@/Components/Play/CompassScreen.vue';
import MapScreen from '@/Components/Play/MapScreen.vue';
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

    <FinishScreen v-if="finished" />

    <!-- No current location to play (e.g. a published game with zero locations,
         or locations removed mid-session so the player's index now overshoots).
         Reuse FinishScreen as a neutral terminal state rather than crashing on
         a null location prop. -->
    <FinishScreen v-else-if="!currentLocation" />

    <RewardOverlay v-else-if="showingReward" @done="onRewardDone" />

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

    <div v-else class="relative h-screen">
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
            class="absolute bottom-6 right-6 z-[1000] rounded-full bg-white p-4 text-2xl shadow-lg"
        >
            {{ viewMode === 'compass' ? '🗺️' : '🧭' }}
        </button>
    </div>
</template>
