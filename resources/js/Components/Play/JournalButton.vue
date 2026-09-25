<script setup>
import BookIcon from '@/Components/Play/BookIcon.vue';

// Opens the journal of stations already passed (their text boards).
defineEmits(['open']);
</script>

<template>
    <button
        type="button"
        aria-label="Prošlá stanoviště"
        class="absolute bottom-[max(1.5rem,env(safe-area-inset-bottom))] left-5 z-[1000] flex h-16 w-16 items-center justify-center rounded-full bg-white text-teal-700 shadow-xl ring-4 ring-white/60 active:scale-95 motion-safe:transition-transform"
        @click="$emit('open')"
    >
        <BookIcon class="h-9 w-9" page-class="fill-teal-100" />
    </button>
</template>
