<script setup>
import { ref } from 'vue';
import ErrorFlash from '@/Components/Play/ErrorFlash.vue';

const props = defineProps({
    images: { type: Array, required: true },
    gameSlug: { type: String, required: true },
    locationId: { type: Number, required: true },
});
const emit = defineEmits(['completed']);

const submitting = ref(false);
const hasError = ref(false);

const pick = async (index) => {
    if (submitting.value) return;

    submitting.value = true;
    hasError.value = false;

    try {
        const response = await window.axios.post(
            route('play.attempts.answer', [props.gameSlug, props.locationId]),
            { selected_index: index },
        );

        if (response.data.correct) {
            emit('completed', response.data.player);
        }
    } catch (error) {
        console.error('Failed to submit answer', error);
        hasError.value = true;
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <div class="flex h-screen flex-col items-center justify-center gap-6 bg-white p-6">
        <ErrorFlash :show="hasError" />
        <div class="grid grid-cols-2 gap-4">
            <button
                v-for="(image, index) in images"
                :key="index"
                type="button"
                :disabled="submitting"
                @click="pick(index)"
                class="rounded-2xl border-4 border-transparent active:border-sky-500 disabled:opacity-50"
            >
                <img :src="image" class="h-40 w-40 rounded-xl object-cover" />
            </button>
        </div>
    </div>
</template>
