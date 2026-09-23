<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    games: Array,
});

const STATUS_LABELS = { draft: 'koncept', published: 'publikováno', archived: 'archivováno' };

const pendingDelete = ref(null);
const deleting = ref(false);

const togglePublish = (game) => {
    const routeName = game.status === 'published' ? 'admin.games.unpublish' : 'admin.games.publish';
    router.patch(route(routeName, game.id), {}, { preserveScroll: true });
};

const confirmDelete = () => {
    deleting.value = true;
    router.delete(route('admin.games.destroy', pendingDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            pendingDelete.value = null;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
};

const deleteMessage = computed(() => (pendingDelete.value ? `Smazat hru \u201e${pendingDelete.value.name}\u201c?` : ''));
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
                        <li v-for="game in games" :key="game.id" class="flex flex-wrap items-center justify-between gap-3 border-b p-4 last:border-0">
                            <div>
                                <Link :href="route('admin.games.edit', game.id)" class="font-medium text-indigo-600">
                                    {{ game.name }}
                                </Link>
                                <span
                                    class="ml-2 rounded px-2 py-0.5 text-xs"
                                    :class="game.status === 'published' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ STATUS_LABELS[game.status] ?? game.status }}
                                </span>
                            </div>
                            <div class="flex items-center gap-4 text-sm">
                                <Link :href="route('admin.games.edit', game.id)" class="text-indigo-600">Upravit</Link>
                                <button
                                    type="button"
                                    class="disabled:cursor-not-allowed disabled:text-gray-400"
                                    :class="game.status === 'published' ? 'text-gray-700' : 'text-green-700'"
                                    :disabled="game.status !== 'published' && game.locations_count === 0"
                                    :title="game.status !== 'published' && game.locations_count === 0 ? 'Nejdřív přidej stanoviště' : ''"
                                    @click="togglePublish(game)"
                                >
                                    {{ game.status === 'published' ? 'Skrýt' : 'Publikovat' }}
                                </button>
                                <Link :href="route('admin.games.tracking', game.id)" class="text-indigo-600">Sledovat hráče</Link>
                                <button type="button" class="ml-4 text-red-400 hover:text-red-600" @click="pendingDelete = game">
                                    Smazat
                                </button>
                            </div>
                        </li>
                        <li v-if="games.length === 0" class="p-4 text-gray-500">Zatím žádné hry.</li>
                    </ul>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="pendingDelete !== null"
            :message="deleteMessage"
            warning="Smažou se i všechna stanoviště, hráči a jejich fotky. Tato akce je nevratná."
            :processing="deleting"
            @confirm="confirmDelete"
            @cancel="pendingDelete = null"
        />
    </AuthenticatedLayout>
</template>
