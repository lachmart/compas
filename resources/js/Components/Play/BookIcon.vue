<script setup>
// Open book: the journal / station text. Size and colour come from the
// parent's class; `pageClass` optionally fills the pages.
defineProps({
    pageClass: { type: String, default: '' },
});
</script>

<template>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
        <path d="M12 6.5C10 5 7 4.5 3.5 5v13c3.5-.5 6.5 0 8.5 1.5 2-1.5 5-2 8.5-1.5V5C17 4.5 14 5 12 6.5Z" :class="pageClass" />
        <path d="M12 6.5v13" />
    </svg>
</template>
