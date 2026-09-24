<script setup>
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
        <!-- Open book -->
        <svg viewBox="0 0 24 24" class="h-9 w-9" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
            <path d="M12 6.5C10 5 7 4.5 3.5 5v13c3.5-.5 6.5 0 8.5 1.5 2-1.5 5-2 8.5-1.5V5C17 4.5 14 5 12 6.5Z" class="fill-teal-100" />
            <path d="M12 6.5v13" />
        </svg>
    </button>
</template>
