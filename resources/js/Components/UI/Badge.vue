<script setup>
defineProps({
    tone: { type: String, default: 'slate' },
});

const TONE_CLASSES = {
    green: 'bg-green-100 text-green-800',
    slate: 'bg-slate-100 text-slate-700',
    amber: 'bg-amber-100 text-amber-800',
    teal: 'bg-teal-100 text-teal-800',
    orange: 'bg-orange-100 text-orange-800',
    red: 'bg-red-100 text-red-800',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="TONE_CLASSES[tone] ?? TONE_CLASSES.slate"
    >
        <slot />
    </span>
</template>
