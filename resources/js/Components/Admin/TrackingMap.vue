<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { isStale, progressLabel } from '@/lib/tracking';

const props = defineProps({
    locations: { type: Array, required: true },
    players: { type: Array, required: true },
    now: { type: Number, required: true },
});
const emit = defineEmits(['select']);

const mapEl = ref(null);
let map;
const playerMarkers = new Map();

const styleFor = (player) => {
    const stale = isStale(player, props.now);

    return {
        radius: 9,
        color: stale ? '#9ca3af' : player.color,
        fillColor: stale ? '#d1d5db' : player.color,
        fillOpacity: stale ? 0.5 : 0.9,
        weight: 2,
    };
};

const syncPlayers = () => {
    if (!map) return;

    props.players.forEach((player) => {
        if (!player.position) return;

        const latLng = [player.position.lat, player.position.lng];
        let marker = playerMarkers.get(player.id);

        if (!marker) {
            marker = L.circleMarker(latLng, styleFor(player))
                .addTo(map)
                .on('click', () => emit('select', player.id));
            marker.bindTooltip('', { permanent: true, direction: 'top', offset: [0, -8] });
            playerMarkers.set(player.id, marker);
        }

        marker.setLatLng(latLng);
        marker.setStyle(styleFor(player));
        marker.setTooltipContent(`${player.nickname} · ${progressLabel(player)}`);
    });
};

onMounted(() => {
    map = L.map(mapEl.value);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    const bounds = [];
    props.locations.forEach((location) => {
        L.marker([location.lat, location.lng], {
            icon: L.divIcon({
                className: '',
                html: `<div class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white shadow">${location.position}</div>`,
                iconSize: [28, 28],
            }),
        }).addTo(map);
        bounds.push([location.lat, location.lng]);
    });

    if (bounds.length) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 17 });
    } else {
        map.setView([49.8, 15.5], 7);
    }

    syncPlayers();
});

onBeforeUnmount(() => map?.remove());

watch(() => [props.players, props.now], syncPlayers);
</script>

<template>
    <div ref="mapEl" class="h-full w-full"></div>
</template>
