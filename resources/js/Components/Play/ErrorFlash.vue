<script setup>
defineProps({
    show: { type: Boolean, default: false },
});
</script>

<template>
    <!-- Sits just below the player HUD. -->
    <div
        v-if="show"
        class="pointer-events-none fixed inset-x-0 top-[max(6.5rem,calc(env(safe-area-inset-top)+5.5rem))] z-[1100] flex justify-center"
        role="alert"
        aria-label="Chyba"
    >
        <div
            class="flex h-20 w-20 items-center justify-center rounded-full bg-red-500 text-white shadow-xl ring-4 ring-white motion-safe:animate-shake-soft"
        >
            <svg viewBox="0 0 24 24" class="h-12 w-12" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" aria-hidden="true">
                <path d="M12 4.5v9" />
                <circle cx="12" cy="19" r="0.6" fill="currentColor" stroke-width="2.5" />
            </svg>
        </div>
    </div>
</template>
