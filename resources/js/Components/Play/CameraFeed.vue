<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

// Live back-camera picture behind the AR aim. Emits 'unavailable' when
// there is no camera or the player refuses it; the caller then keeps its
// plain background.
const emit = defineEmits(['unavailable']);

const video = ref(null);
let stream = null;
let stopped = false;

onMounted(async () => {
    if (!navigator.mediaDevices?.getUserMedia) {
        emit('unavailable');

        return;
    }

    try {
        const media = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        });

        // Unmounted while the permission prompt was open.
        if (stopped) {
            media.getTracks().forEach((track) => track.stop());

            return;
        }

        stream = media;
        video.value.srcObject = media;
        await video.value.play().catch(() => {});
    } catch (error) {
        console.warn('Camera unavailable', error);
        emit('unavailable');
    }
});

onUnmounted(() => {
    stopped = true;
    stream?.getTracks().forEach((track) => track.stop());
    stream = null;
});
</script>

<template>
    <!-- muted + playsinline: iOS plays inline without a gesture. -->
    <video ref="video" class="h-full w-full object-cover" autoplay muted playsinline aria-hidden="true"></video>
</template>
