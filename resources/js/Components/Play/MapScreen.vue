<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { useSensors } from '@/lib/useSensors';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconRetinaUrl: markerIcon2x, iconUrl: markerIcon, shadowUrl: markerShadow });

const props = defineProps({
    locations: { type: Array, required: true },
    currentLocationId: { type: Number, required: true },
});

const { position, geoError, requestPermissions } = useSensors();
requestPermissions();

const mapEl = ref(null);
let map;
let playerMarker;
let trail = [];
let trailLine;
const markersByLocationId = new Map();

const targetIcon = L.divIcon({ className: 'text-2xl', html: '📍', iconSize: [30, 30] });
const defaultIcon = new L.Icon.Default();

onMounted(() => {
    const first = props.locations[0];
    map = L.map(mapEl.value).setView([first.lat, first.lng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    props.locations.forEach((location) => {
        const isCurrent = location.id === props.currentLocationId;
        const marker = L.marker([location.lat, location.lng], isCurrent ? { icon: targetIcon } : {}).addTo(map);
        markersByLocationId.set(location.id, marker);
    });

    trailLine = L.polyline([], { color: 'blue' }).addTo(map);
});

onBeforeUnmount(() => map?.remove());

watch(
    () => props.currentLocationId,
    (newId, oldId) => {
        markersByLocationId.get(oldId)?.setIcon(defaultIcon);
        markersByLocationId.get(newId)?.setIcon(targetIcon);
    },
);

watch(position, (value) => {
    if (!value || !map) return;

    if (playerMarker) {
        playerMarker.setLatLng([value.lat, value.lng]);
    } else {
        playerMarker = L.circleMarker([value.lat, value.lng], { radius: 8, color: 'green' }).addTo(map);

        // Frame the player and their target once, so the player's own marker
        // isn't off-screen; later panning by the player is left alone.
        const target = props.locations.find((location) => location.id === props.currentLocationId);
        if (target) {
            map.fitBounds([[value.lat, value.lng], [target.lat, target.lng]], { padding: [40, 40], maxZoom: 17 });
        }
    }

    trail.push([value.lat, value.lng]);
    trailLine.setLatLngs(trail);
});
</script>

<template>
    <div class="relative h-screen w-full">
        <div ref="mapEl" class="h-full w-full"></div>
        <button
            v-if="geoError"
            type="button"
            @click="requestPermissions"
            class="absolute left-4 top-4 z-[1000] flex h-12 w-12 items-center justify-center rounded-full bg-red-500 text-2xl text-white shadow-lg"
        >
            ⚠️
        </button>
    </div>
</template>
