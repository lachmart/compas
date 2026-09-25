<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { acquireCamera, pauseCamera } from '@/lib/cameraStream';

// Live back-camera picture behind the AR aim, from the game's one shared
// stream (paused, not closed, when this view goes away — see
// cameraStream.js). Emits 'unavailable' when there is no camera or the
// player refuses it; the caller then keeps its plain background.
const emit = defineEmits(['unavailable']);

const video = ref(null);
let unmounted = false;

onMounted(async () => {
    try {
        const stream = await acquireCamera();

        // Unmounted while the permission prompt was open.
        if (unmounted) {
            pauseCamera();

            return;
        }

        video.value.srcObject = stream;
        await video.value.play().catch(() => {});
    } catch (error) {
        console.warn('Camera unavailable', error);
        emit('unavailable');
    }
});

onUnmounted(() => {
    unmounted = true;
    if (video.value) video.value.srcObject = null;
    pauseCamera();
});
</script>

<template>
    <!-- muted + playsinline: iOS plays inline without a gesture. -->
    <video ref="video" class="h-full w-full object-cover" autoplay muted playsinline aria-hidden="true"></video>
</template>
