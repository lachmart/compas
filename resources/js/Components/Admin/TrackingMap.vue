<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { avatarEmoji } from '@/lib/avatars';
import { numberedIcon, playerIcon, TEAL } from '@/lib/mapMarkers';
import { formatTime, isStale, progressLabel } from '@/lib/tracking';

const props = defineProps({
    locations: { type: Array, required: true },
    players: { type: Array, required: true },
    now: { type: Number, required: true },
});
const emit = defineEmits(['select']);

const mapEl = ref(null);
let map;
let resizeObserver = null;
const playerMarkers = new Map();

const CZ_CENTER = [49.8, 15.5];
const FIT_OPTIONS = { padding: [60, 60], maxZoom: 17 };

const iconState = (player) => ({ avatar: player.avatar, color: player.color, stale: isStale(player, props.now) });
const iconKey = ({ avatar, color, stale }) => `${avatar}|${color}|${stale}`;
const iconFor = (player) => playerIcon(L, iconState(player));

const syncPlayers = () => {
    if (!map) return;

    props.players.forEach((player) => {
        if (!player.position) return;

        const latLng = [player.position.lat, player.position.lng];
        let marker = playerMarkers.get(player.id);

        if (!marker) {
            marker = L.marker(latLng, { icon: iconFor(player), keyboard: false })
                .addTo(map)
                .on('click', () => emit('select', player.id));
            marker.bindTooltip('', { permanent: true, direction: 'top', offset: [0, -18] });
            marker.kompasIconKey = iconKey(iconState(player));
            playerMarkers.set(player.id, marker);
        }

        marker.setLatLng(latLng);

        // setIcon rebuilds the marker's DOM; only do it when the look changed
        // (every tick would otherwise re-create all markers).
        const key = iconKey(iconState(player));
        if (marker.kompasIconKey !== key) {
            marker.setIcon(iconFor(player));
            marker.kompasIconKey = key;
        }

        // Spec §6: a stale marker shows "naposledy viděn v HH:MM".
        const tooltip = isStale(player, props.now)
            ? `${avatarEmoji(player.avatar)} ${player.nickname} · ${progressLabel(player)} · naposledy viděn v ${formatTime(player.last_seen_at)}`
            : `${avatarEmoji(player.avatar)} ${player.nickname} · ${progressLabel(player)}`;
        // Pass DOM text node to Leaflet; strings are parsed as HTML, so user-controlled content must use createTextNode to prevent XSS.
        marker.setTooltipContent(document.createTextNode(tooltip));
    });
};

const fitAll = () => {
    if (!map) return;

    const points = props.locations.map((l) => [l.lat, l.lng]);
    props.players.forEach((player) => {
        if (player.position) points.push([player.position.lat, player.position.lng]);
    });

    if (points.length) map.fitBounds(points, FIT_OPTIONS);
};

onMounted(() => {
    map = L.map(mapEl.value);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    // Same look as the editor's GameMap: dashed route + numbered markers, no radii.
    const route = props.locations.map((location) => [location.lat, location.lng]);
    if (route.length > 1) {
        L.polyline(route, { color: TEAL, weight: 3, opacity: 0.8, dashArray: '6 8', interactive: false }).addTo(map);
    }

    props.locations.forEach((location) => {
        // numberedIcon's HTML only ever contains an integer.
        L.marker([location.lat, location.lng], { icon: numberedIcon(L, location.position), keyboard: false })
            // Pass DOM text node to Leaflet; strings are parsed as HTML, so user-controlled content must use createTextNode to prevent XSS.
            .bindTooltip(document.createTextNode(location.name || `Stanoviště ${location.position}`), { direction: 'top', offset: [0, -14] })
            .addTo(map);
    });

    if (route.length) {
        map.fitBounds(route, FIT_OPTIONS);
    } else {
        map.setView(CZ_CENTER, 7);
    }

    syncPlayers();

    // The panel layout resizes the map (mobile ↔ desktop).
    resizeObserver = new ResizeObserver(() => map?.invalidateSize());
    resizeObserver.observe(mapEl.value);
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    resizeObserver = null;
    map?.remove();
    map = null;
});

watch(() => [props.players, props.now], syncPlayers);

defineExpose({ fitAll });
</script>

<template>
    <div ref="mapEl" class="h-full w-full"></div>
</template>
