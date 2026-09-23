<script setup>
defineProps({
    title: { type: String, default: null },
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div v-if="title || $slots.actions" class="flex items-center justify-between px-5 pt-5">
            <h3 v-if="title" class="text-base font-semibold text-slate-900">{{ title }}</h3>
            <div v-if="$slots.actions" class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
        <div :class="padded ? 'p-5' : ''">
            <slot />
        </div>
    </div>
</template>
