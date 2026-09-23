<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, required: true },
});
</script>

<template>
    <Link
        :href="href"
        class="flex w-full items-center justify-center rounded-xl border-2 border-teal-600 px-4 py-2.5 text-base font-semibold text-teal-700 transition hover:bg-teal-50 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
    >
        <slot />
    </Link>
</template>
