<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import GameCard from '@/Components/Admin/GameCard.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps({
    games: Array,
});

const FIRST_STEPS = ['Pojmenuj hru', 'Rozmísti stanoviště na mapě', 'Pošli dětem odkaz nebo QR kód'];

// Ticks so "Naposledy aktivní před 5 min" doesn't go stale while the page is open.
const now = ref(Date.now());
let nowTimer = null;

onMounted(() => {
    nowTimer = setInterval(() => {
        now.value = Date.now();
    }, 30000);
});

onBeforeUnmount(() => {
    clearInterval(nowTimer);
});

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
        <template #header>Moje hry</template>

        <template #actions>
            <Button :href="route('admin.games.create')">
                <template #icon>
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M10 4a1 1 0 0 1 1 1v4h4a1 1 0 1 1 0 2h-4v4a1 1 0 1 1-2 0v-4H5a1 1 0 1 1 0-2h4V5a1 1 0 0 1 1-1Z" />
                    </svg>
                </template>
                Nová hra
            </Button>
        </template>

        <div v-if="games.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <GameCard
                v-for="game in games"
                :key="game.id"
                :game="game"
                :now="now"
                @toggle-publish="togglePublish"
                @delete="pendingDelete = $event"
            />
        </div>

        <Card v-else class="overflow-hidden bg-gradient-to-br from-white via-white to-teal-50/60">
            <EmptyState
                title="Vytvoř svou první hru"
                text="Hra je trasa stanovišť, ke kterým děti dojdou podle kompasu."
            >
                <template #icon>
                    <ApplicationLogo class="h-10 w-10" />
                </template>

                <!-- Numbered steps echo the stations on a route preview: first one orange. -->
                <ol class="mt-8 flex w-fit max-w-2xl flex-col gap-4 text-left sm:w-full sm:flex-row sm:gap-0">
                    <li
                        v-for="(step, index) in FIRST_STEPS"
                        :key="step"
                        class="relative flex items-center gap-3 sm:flex-1 sm:flex-col sm:gap-2 sm:text-center"
                    >
                        <span
                            v-if="index > 0"
                            class="absolute right-1/2 top-4 hidden w-full border-t-2 border-dashed border-teal-300 sm:block"
                            aria-hidden="true"
                        />
                        <span
                            class="relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white ring-4 ring-white"
                            :class="index === 0 ? 'bg-orange-500' : 'bg-teal-600'"
                        >
                            {{ index + 1 }}
                        </span>
                        <span class="text-sm font-medium text-slate-700 sm:px-2">{{ step }}</span>
                    </li>
                </ol>

                <template #actions>
                    <Button :href="route('admin.games.create')">
                        <template #icon>
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M10 4a1 1 0 0 1 1 1v4h4a1 1 0 1 1 0 2h-4v4a1 1 0 1 1-2 0v-4H5a1 1 0 1 1 0-2h4V5a1 1 0 0 1 1-1Z" />
                            </svg>
                        </template>
                        Nová hra
                    </Button>
                </template>
            </EmptyState>
        </Card>

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
