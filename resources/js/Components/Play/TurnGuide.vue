<script setup>
import { watch } from 'vue';

const props = defineProps({
    guidance: {
        type: String,
        required: true,
        validator: (value) => ['on_course', 'left', 'right', 'behind'].includes(value),
    },
});

// One short buzz the moment the child turns onto course; not repeated
// while they stay on it. iOS ignores web vibration — the target still shows.
watch(
    () => props.guidance,
    (value, previous) => {
        if (value === 'on_course' && previous !== 'on_course') {
            navigator.vibrate?.(150);
        }
    },
    { immediate: true },
);
</script>

<template>
    <svg
        v-if="guidance === 'on_course'"
        viewBox="0 0 24 24"
        class="h-40 w-40 animate-pulse text-gray-800"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
    >
        <circle cx="12" cy="12" r="10" />
        <circle cx="12" cy="12" r="6" />
        <circle cx="12" cy="12" r="2" fill="currentColor" />
    </svg>

    <svg
        v-else-if="guidance === 'left'"
        viewBox="0 0 24 24"
        class="h-40 w-40 text-gray-800"
        fill="none"
        stroke="currentColor"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <path d="M20 12H5M11 6l-6 6 6 6" />
    </svg>

    <svg
        v-else-if="guidance === 'right'"
        viewBox="0 0 24 24"
        class="h-40 w-40 text-gray-800"
        fill="none"
        stroke="currentColor"
        stroke-width="3"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <path d="M4 12h15M13 6l6 6-6 6" />
    </svg>

    <svg
        v-else
        viewBox="0 0 24 24"
        class="h-40 w-40 text-gray-800"
        fill="none"
        stroke="currentColor"
        stroke-width="2.5"
        stroke-linecap="round"
        stroke-linejoin="round"
    >
        <path d="M8 20V10a4 4 0 0 1 8 0v5" />
        <path d="M13 12l3 3 3-3" />
    </svg>
</template>
