<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    tabs: { type: Array, required: true },
});
</script>

<template>
    <div class="inline-flex rounded-xl bg-slate-100 p-1">
        <Link
            v-for="tab in tabs"
            :key="tab.label"
            :href="tab.href"
            :aria-current="tab.active ? 'page' : undefined"
            class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
            :class="tab.active ? 'bg-white text-teal-800 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
        >
            {{ tab.label }}
        </Link>
    </div>
</template>
