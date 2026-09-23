<script setup>
const props = defineProps({
    label: { type: String, required: true },
    hint: { type: String, default: null },
    error: { type: String, default: null },
    for: { type: String, default: null },
});
</script>

<template>
    <div>
        <label v-if="label" :for="props.for" class="text-sm font-medium text-slate-700">{{ label }}</label>
        <p v-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
        <div class="mt-1">
            <slot />
        </div>
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
