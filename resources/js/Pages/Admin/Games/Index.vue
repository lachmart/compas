<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    games: Array,
});
</script>

<template>
    <Head title="Moje hry" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Moje hry</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <Link :href="route('admin.games.create')" class="mb-4 inline-block rounded bg-indigo-600 px-4 py-2 text-white">
                    Nová hra
                </Link>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <ul>
                        <li v-for="game in games" :key="game.id" class="flex items-center justify-between border-b p-4 last:border-0">
                            <div>
                                <Link :href="route('admin.games.edit', game.id)" class="font-medium text-indigo-600">
                                    {{ game.name }}
                                </Link>
                                <span class="ml-2 text-sm text-gray-500">({{ game.status }})</span>
                            </div>
                        </li>
                        <li v-if="games.length === 0" class="p-4 text-gray-500">Zatím žádné hry.</li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
