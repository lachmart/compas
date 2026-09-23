<script setup>
import { computed } from 'vue';
import Badge from '@/Components/UI/Badge.vue';
import { avatarEmoji } from '@/lib/avatars';
import { relativeTime } from '@/lib/format';
import { isStale } from '@/lib/tracking';

const props = defineProps({
    player: { type: Object, required: true },
    now: { type: Number, required: true },
    selected: { type: Boolean, default: false },
});
const emit = defineEmits(['select']);

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
    <button
        type="button"
        class="w-full rounded-xl px-3 py-3 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
        :class="selected ? 'bg-orange-50 ring-1 ring-orange-200' : 'hover:bg-slate-50'"
        :aria-pressed="selected"
        @click="emit('select', player.id)"
    >
        <div class="flex items-center gap-3">
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

        <div class="ml-6 mt-2">
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
</template>
