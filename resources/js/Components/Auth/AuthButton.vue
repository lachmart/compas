<script setup>
defineProps({
    processing: { type: Boolean, default: false },
});
</script>

<template>
    <button
        type="submit"
        :disabled="processing"
        class="flex w-full items-center justify-center rounded-xl bg-teal-600 px-4 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60"
    >
        <slot />
    </button>
</template>
