<script setup>
defineProps({
    title: { type: String, required: true },
    text: { type: String, default: null },
});
</script>

<template>
    <div class="flex flex-col items-center px-6 py-12 text-center">
        <div
            v-if="$slots.icon"
            class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-teal-50 text-teal-700"
        >
            <slot name="icon" />
        </div>
        <h3 class="text-lg font-semibold text-slate-900">{{ title }}</h3>
        <p v-if="text" class="mt-1 max-w-sm text-slate-600">{{ text }}</p>
        <slot />
        <div v-if="$slots.actions" class="mt-6">
            <slot name="actions" />
        </div>
    </div>
</template>
