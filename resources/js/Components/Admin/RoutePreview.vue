<script setup>
import { computed } from 'vue';
import { pluralize } from '@/lib/format';
import { projectPoints } from '@/lib/routePreview';

const props = defineProps({
    // Station positions as [lat, lng] in game order.
    route: { type: Array, default: () => [] },
});

const WIDTH = 160;
const HEIGHT = 90;
const PADDING = 12;

const points = computed(() => projectPoints(props.route, WIDTH, HEIGHT, PADDING));

const polyline = computed(() => points.value.map(({ x, y }) => `${x.toFixed(2)},${y.toFixed(2)}`).join(' '));
</script>

<template>
    <div class="relative aspect-video w-full bg-gradient-to-br from-emerald-50 via-teal-50 to-sky-50">
        <svg
            viewBox="0 0 160 90"
            class="absolute inset-0 h-full w-full"
            role="img"
            :aria-label="points.length ? `Náhled trasy: ${pluralize(points.length, 'stanoviště', 'stanoviště', 'stanovišť')}` : 'Zatím žádná stanoviště'"
        >
            <!-- Decorative contour lines, same motif as the sign-in pages. -->
            <g fill="none" class="stroke-teal-700/15" stroke-width="0.6">
                <path d="M-5 22 C 25 10, 55 32, 85 20 S 140 6, 165 18" />
                <path d="M-5 72 C 30 60, 60 84, 95 70 S 145 58, 165 70" />
                <ellipse cx="126" cy="46" rx="18" ry="11" />
                <ellipse cx="126" cy="46" rx="29" ry="18" />
            </g>

            <template v-if="points.length">
                <polyline
                    v-if="points.length > 1"
                    :points="polyline"
                    fill="none"
                    class="stroke-teal-600"
                    stroke-width="1.2"
                    stroke-dasharray="3 2.4"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <g v-for="(point, index) in points" :key="index">
                    <circle
                        :cx="point.x"
                        :cy="point.y"
                        r="5"
                        :class="index === 0 ? 'fill-orange-500' : 'fill-teal-600'"
                        class="stroke-white"
                        stroke-width="1.1"
                    />
                    <text
                        :x="point.x"
                        :y="point.y"
                        text-anchor="middle"
                        dominant-baseline="central"
                        class="fill-white font-bold"
                        :font-size="index >= 9 ? 4.4 : 5.5"
                    >
                        {{ index + 1 }}
                    </text>
                </g>
            </template>
        </svg>

        <div v-if="!points.length" class="absolute inset-0 flex items-center justify-center">
            <span class="rounded-full bg-white/80 px-3 py-1 text-sm font-medium text-teal-800/80 ring-1 ring-teal-900/5">
                Zatím žádná stanoviště
            </span>
        </div>
    </div>
</template>
