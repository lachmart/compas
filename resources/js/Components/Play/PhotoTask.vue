<script setup>
import { ref } from 'vue';
import ErrorFlash from '@/Components/Play/ErrorFlash.vue';

const props = defineProps({
    referenceImage: { type: String, required: true },
    taskType: { type: String, required: true },
    gameSlug: { type: String, required: true },
    locationId: { type: Number, required: true },
});
const emit = defineEmits(['completed']);

const capture = props.taskType === 'selfie_doing_something' ? 'user' : 'environment';

const submitting = ref(false);
const hasError = ref(false);

const onCapture = async (event) => {
    const file = event.target.files[0];
    event.target.value = '';
    if (!file || submitting.value) return;

    submitting.value = true;
    hasError.value = false;

    const formData = new FormData();
    formData.append('photo', file);

    try {
        const response = await window.axios.post(
            route('play.attempts.photo', [props.gameSlug, props.locationId]),
            formData,
        );

        emit('completed', response.data.player);
    } catch (error) {
        console.error('Failed to upload photo', error);
        hasError.value = true;
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <div class="flex h-screen flex-col items-center justify-center gap-6 bg-white p-6">
        <ErrorFlash :show="hasError" />
        <img :src="referenceImage" class="h-56 w-56 rounded-xl object-cover" />
        <label class="rounded-full bg-sky-600 px-8 py-4 text-3xl text-white active:scale-95" :class="{ 'pointer-events-none opacity-50': submitting }">
            📷
            <input type="file" accept="image/*" :capture="capture" class="hidden" :disabled="submitting" @change="onCapture" />
        </label>
    </div>
</template>
