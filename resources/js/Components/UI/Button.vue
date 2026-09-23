<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Spinner from './Spinner.vue';

const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    href: { type: String, default: null },
    type: { type: String, default: 'button' },
    processing: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const VARIANT_CLASSES = {
    primary: 'bg-teal-600 text-white hover:bg-teal-700',
    secondary: 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50',
    ghost: 'text-slate-600 hover:bg-slate-100',
    danger: 'bg-red-600 text-white hover:bg-red-700',
};

const SIZE_CLASSES = {
    md: 'px-4 py-2.5 text-sm',
    sm: 'px-3 py-1.5 text-sm',
};

const tag = computed(() => (props.href ? Link : 'button'));
</script>

<template>
    <component
        :is="tag"
        :href="href ?? undefined"
        :type="href ? undefined : type"
        :disabled="!href && processing ? true : undefined"
        class="inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        :class="[
            VARIANT_CLASSES[variant] ?? VARIANT_CLASSES.primary,
            SIZE_CLASSES[size] ?? SIZE_CLASSES.md,
            block ? 'w-full' : '',
        ]"
    >
        <Spinner v-if="processing" class="h-4 w-4" />
        <slot v-else name="icon" />
        <slot />
    </component>
</template>
