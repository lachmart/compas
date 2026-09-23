<script setup>
import { onMounted } from 'vue';

const emit = defineEmits(['done']);

onMounted(() => {
    setTimeout(() => emit('done'), 1200);
});
</script>

<template>
    <div class="flex h-screen flex-col items-center justify-center bg-green-100">
        <div class="animate-bounce text-8xl">🎉</div>
    </div>
</template>
