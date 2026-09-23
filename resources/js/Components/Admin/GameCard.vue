<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import RoutePreview from '@/Components/Admin/RoutePreview.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import { pluralize, relativeTime } from '@/lib/format';
import { gameStatusLabel, gameStatusTone } from '@/lib/gameStatus';

const props = defineProps({
    game: { type: Object, required: true },
    // Current time in ms, ticked by the page so "před 5 min" stays fresh.
    now: { type: Number, required: true },
});

const emit = defineEmits(['toggle-publish', 'delete']);

const isPublished = computed(() => props.game.status === 'published');
const cannotPublish = computed(() => !isPublished.value && props.game.locations_count === 0);

const stats = computed(() => {
    const g = props.game;
    const items = [{ key: 'locations', text: pluralize(g.locations_count, 'stanoviště', 'stanoviště', 'stanovišť') }];

    if (g.players_count > 0) {
        items.push({ key: 'players', text: pluralize(g.players_count, 'hráč', 'hráči', 'hráčů') });
    }
    if (g.active_players_count > 0) {
        items.push({ key: 'active', text: pluralize(g.active_players_count, 'právě hraje', 'právě hrají', 'právě hraje') });
    }
    if (g.finished_players_count > 0) {
        items.push({ key: 'finished', text: pluralize(g.finished_players_count, 'dokončil', 'dokončili', 'dokončilo') });
    }

    return items;
});

const lastActivity = computed(() =>
    props.game.last_activity_at ? `Naposledy aktivní ${relativeTime(props.game.last_activity_at, props.now)}` : 'Zatím nikdo nehrál',
);

// The [&>div] classes on Card stretch its single slot wrapper so footers
// line up across a grid row.
const menuItemClasses =
    'block w-full px-4 py-2 text-start text-sm leading-5 transition duration-150 ease-in-out focus:outline-none disabled:cursor-not-allowed disabled:text-slate-400';
</script>

<template>
    <Card :padded="false" class="flex flex-col transition hover:shadow-md [&>div]:flex [&>div]:flex-1 [&>div]:flex-col">
        <Link
            :href="route('admin.games.edit', game.id)"
            class="block overflow-hidden rounded-t-2xl"
            tabindex="-1"
            aria-hidden="true"
        >
            <RoutePreview :route="game.route" />
        </Link>

        <div class="flex flex-1 flex-col gap-3 border-t border-slate-100 p-5">
            <div class="flex items-start justify-between gap-3">
                <Link
                    :href="route('admin.games.edit', game.id)"
                    class="min-w-0 break-words text-lg font-semibold leading-snug text-slate-900 hover:text-teal-700 focus:outline-none focus-visible:underline"
                >
                    {{ game.name }}
                </Link>
                <Badge :tone="gameStatusTone(game.status)" class="mt-0.5 shrink-0">{{ gameStatusLabel(game.status) }}</Badge>
            </div>

            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-600">
                <template v-for="(item, index) in stats" :key="item.key">
                    <span v-if="index > 0" class="text-slate-400" aria-hidden="true">&middot;</span>
                    <span
                        :class="item.key === 'active' ? 'inline-flex items-center gap-1.5 font-medium text-green-700' : ''"
                    >
                        <span v-if="item.key === 'active'" class="relative flex h-2 w-2" aria-hidden="true">
                            <span class="absolute inline-flex h-full w-full motion-safe:animate-ping rounded-full bg-green-400 opacity-75" />
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500" />
                        </span>
                        {{ item.text }}
                    </span>
                </template>
            </p>

            <p class="text-xs text-slate-500">{{ lastActivity }}</p>
        </div>

        <div class="flex items-center gap-2 border-t border-slate-100 px-5 py-3">
            <Button size="sm" :href="route('admin.games.edit', game.id)">Upravit</Button>
            <Button size="sm" variant="secondary" :href="route('admin.games.tracking', game.id)">Sledovat</Button>

            <div class="ml-auto">
                <Dropdown align="right" width="48" content-classes="py-1 bg-white">
                    <template #trigger>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                            aria-label="Další akce"
                            title="Další akce"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <circle cx="4" cy="10" r="1.6" />
                                <circle cx="10" cy="10" r="1.6" />
                                <circle cx="16" cy="10" r="1.6" />
                            </svg>
                        </button>
                    </template>

                    <template #content>
                        <button
                            type="button"
                            :class="[menuItemClasses, 'text-slate-700 enabled:hover:bg-slate-100 enabled:focus:bg-slate-100']"
                            :disabled="cannotPublish"
                            :title="cannotPublish ? 'Nejdřív přidej stanoviště' : undefined"
                            @click="emit('toggle-publish', game)"
                        >
                            {{ isPublished ? 'Skrýt' : 'Publikovat' }}
                            <span v-if="cannotPublish" class="block text-xs text-slate-400">Nejdřív přidej stanoviště</span>
                        </button>
                        <button
                            type="button"
                            :class="[menuItemClasses, 'text-red-600 hover:bg-red-50 focus:bg-red-50']"
                            @click="emit('delete', game)"
                        >
                            Smazat
                        </button>
                    </template>
                </Dropdown>
            </div>
        </div>
    </Card>
</template>
