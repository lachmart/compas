<script setup>
const emit = defineEmits(['ready']);

const start = async () => {
    emit('ready');
};
</script>

<template>
    <div class="flex h-screen flex-col items-center justify-center gap-6 bg-sky-100">
        <svg viewBox="0 0 24 24" class="h-24 w-24 text-sky-600" fill="currentColor">
            <path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5Z" />
        </svg>
        <button
            type="button"
            @click="start"
            class="rounded-full bg-sky-600 px-10 py-6 text-2xl text-white shadow-lg active:scale-95"
        >
            ▶
        </button>
    </div>
</template>
