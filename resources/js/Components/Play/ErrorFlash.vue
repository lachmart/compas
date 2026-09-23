<script setup>
defineProps({
    show: { type: Boolean, default: false },
});
</script>

<template>
    <div v-if="show" class="pointer-events-none fixed inset-x-0 top-6 z-50 flex justify-center">
        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-red-500 text-3xl text-white shadow-lg">⚠️</div>
    </div>
</template>
