<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import LocationList from '@/Components/Admin/LocationList.vue';

const props = defineProps({
    game: Object,
    locations: Array,
});

const form = useForm({
    name: props.game.name,
    slug: props.game.slug,
});

const submit = () => form.put(route('admin.games.update', props.game.id));

const togglePublish = () => {
    const routeName = props.game.status === 'published' ? 'admin.games.unpublish' : 'admin.games.publish';
    router.patch(route(routeName, props.game.id));
};
</script>

<template>
    <Head :title="game.name" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ game.name }}</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-4 bg-white p-6 shadow-sm sm:rounded-lg">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Název hry</label>
                        <input v-model="form.name" type="text" class="mt-1 block w-full rounded border-gray-300" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">URL (slug)</label>
                        <input v-model="form.slug" type="text" class="mt-1 block w-full rounded border-gray-300" />
                        <p v-if="form.errors.slug" class="mt-1 text-sm text-red-600">{{ form.errors.slug }}</p>
                    </div>

                    <div class="flex items-center gap-4">
                        <button type="submit" :disabled="form.processing" class="rounded bg-indigo-600 px-4 py-2 text-white">
                            Uložit
                        </button>
                        <button
                            type="button"
                            @click="togglePublish"
                            class="rounded px-4 py-2 disabled:cursor-not-allowed disabled:opacity-50"
                            :class="game.status === 'published' ? 'bg-gray-200' : 'bg-green-600 text-white'"
                            :disabled="game.status !== 'published' && locations.length === 0"
                            :title="game.status !== 'published' && locations.length === 0 ? 'Nejdřív přidej stanoviště' : ''"
                        >
                            {{ game.status === 'published' ? 'Skrýt hru' : 'Publikovat' }}
                        </button>
                        <Link
                            :href="route('admin.games.tracking', game.id)"
                            class="rounded bg-gray-100 px-4 py-2 text-gray-800"
                        >
                            Sledovat hráče
                        </Link>
                    </div>
                </form>

                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="mb-4 text-lg font-medium text-gray-800">Stanoviště</h3>
                    <LocationList :game="game" :locations="locations" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
