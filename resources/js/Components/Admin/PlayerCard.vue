<script setup>
import { computed } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import Badge from '@/Components/UI/Badge.vue';
import { avatarEmoji } from '@/lib/avatars';
import { relativeTime } from '@/lib/format';
import { isStale } from '@/lib/tracking';

const props = defineProps({
    player: { type: Object, required: true },
    now: { type: Number, required: true },
    selected: { type: Boolean, default: false },
    // Selection mode: tapping the card toggles `checked` instead of opening the detail.
    selectionMode: { type: Boolean, default: false },
    checked: { type: Boolean, default: false },
});
const emit = defineEmits(['select', 'toggle', 'hide', 'delete']);

const onClick = () => {
    if (props.selectionMode) emit('toggle', props.player.id);
    else emit('select', props.player.id);
};

const stale = computed(() => isStale(props.player, props.now));
const finished = computed(() => Boolean(props.player.finished_at));

// Completed locations out of the total (the index points at the one in progress).
const done = computed(() => (finished.value ? props.player.locations_count : props.player.current_location_index));
const percent = computed(() => {
    const total = props.player.locations_count;
    if (finished.value) return 100;
    if (!total) return 0;

    return Math.min(100, Math.round((done.value / total) * 100));
});
</script>

<template>
    <div
        class="flex items-start rounded-xl transition"
        :class="(selectionMode ? checked : selected) ? 'bg-orange-50 ring-1 ring-orange-200' : 'hover:bg-slate-50'"
    >
        <button
            type="button"
            class="min-w-0 flex-1 rounded-xl px-3 py-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
            :aria-pressed="selectionMode ? checked : selected"
            @click="onClick"
        >
            <div class="flex items-center gap-3">
                <span
                    v-if="selectionMode"
                    class="-ml-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md ring-1 transition"
                    :class="checked ? 'bg-teal-600 text-white ring-teal-600' : 'bg-white ring-slate-300'"
                    aria-hidden="true"
                >
                    <svg v-if="checked" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                    </svg>
                </span>
                <span
                    class="h-3 w-3 shrink-0 rounded-full ring-2 ring-white"
                    :style="{ backgroundColor: stale ? '#94a3b8' : player.color }"
                    aria-hidden="true"
                />
                <span
                    class="min-w-0 flex-1 truncate font-semibold"
                    :class="stale ? 'text-slate-500' : 'text-slate-900'"
                >{{ avatarEmoji(player.avatar) }} {{ player.nickname }}</span>
                <Badge v-if="stale" tone="amber" class="shrink-0">bez signálu</Badge>
                <span v-else-if="finished" class="shrink-0 text-xs font-semibold text-green-700">✓ dokončil(a)</span>
                <span v-else class="shrink-0 text-xs font-medium tabular-nums text-slate-500">
                    {{ done }}/{{ player.locations_count }}
                </span>
            </div>

            <div class="mt-2" :class="selectionMode ? 'ml-[54px]' : 'ml-6'">
                <div
                    class="h-1.5 overflow-hidden rounded-full bg-slate-100"
                    role="progressbar"
                    :aria-valuenow="done"
                    aria-valuemin="0"
                    :aria-valuemax="player.locations_count"
                    :aria-label="`Splněno ${done} z ${player.locations_count}`"
                >
                    <div
                        class="h-full rounded-full transition-all"
                        :class="finished ? 'bg-green-500' : stale ? 'bg-slate-300' : 'bg-teal-500'"
                        :style="{ width: `${percent}%` }"
                    />
                </div>
                <p class="mt-1.5 text-xs" :class="stale ? 'text-slate-400' : 'text-slate-500'">
                    <template v-if="finished">dokončeno {{ relativeTime(player.finished_at, now) }}</template>
                    <template v-else>naposledy {{ relativeTime(player.last_seen_at, now) }}</template>
                </p>
            </div>
        </button>

        <Dropdown v-if="!selectionMode" align="right" class="shrink-0 pr-1 pt-1.5">
            <template #trigger>
                <button
                    type="button"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                    :aria-label="`Akce pro hráče ${player.nickname}`"
                    aria-haspopup="menu"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M3 10a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0ZM8.5 10a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0ZM15.5 8.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z" />
                    </svg>
                </button>
            </template>
            <template #content>
                <button
                    type="button"
                    class="block w-full px-4 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none"
                    @click="emit('hide', player)"
                >Skrýt</button>
                <button
                    type="button"
                    class="block w-full px-4 py-2 text-left text-sm font-medium text-red-600 transition hover:bg-red-50 focus:bg-red-50 focus:outline-none"
                    @click="emit('delete', player)"
                >Smazat</button>
            </template>
        </Dropdown>
    </div>
</template>
