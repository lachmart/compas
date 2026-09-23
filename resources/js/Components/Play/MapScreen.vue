<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useSensors } from '@/lib/useSensors';
import { childMapScene, playerIcon, stationIcon } from '@/lib/childMapMarkers';

const props = defineProps({
    locations: { type: Array, required: true },
    // Index (0-based) of the station the child is heading to now; earlier
    // stations render as done, later ones as future. Stays in lockstep with
    // PlayerHud's `current`/`completed` props.
    currentIndex: { type: Number, required: true },
    avatar: { type: String, default: null },
});

const { position, geoError, requestPermissions } = useSensors();
requestPermissions();

const mapEl = ref(null);
let map;
let playerMarker;
let trail = [];
let trailLine;
const markersByLocationId = new Map();

const drawStationMarkers = () => {
    childMapScene({ locations: props.locations, currentIndex: props.currentIndex }).forEach((station) => {
        const marker = L.marker([station.lat, station.lng], {
            icon: stationIcon(L, station.state),
            keyboard: false,
        }).addTo(map);
        markersByLocationId.set(station.id, marker);
    });
};

// Clear of the HUD (top, ~100 px) and the zoom/toggle buttons (bottom).
const FRAME_OPTIONS = { paddingTopLeft: [40, 120], paddingBottomRight: [40, 120], maxZoom: 18, animate: false };

onMounted(() => {
    // Start on the station the child is heading to, not the first one.
    const start = props.locations[props.currentIndex] ?? props.locations[0];
    // The zoom control defaults to the top-left, right under the HUD's
    // avatar bubble; moved to the bottom-left, clear of both the HUD and the
    // compass/map toggle button (bottom-right).
    map = L.map(mapEl.value, { zoomControl: false }).setView([start.lat, start.lng], 17);
    L.control.zoom({ position: 'bottomleft' }).addTo(map);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    drawStationMarkers();

    trailLine = L.polyline([], { color: '#0d9488', weight: 4, opacity: 0.7 }).addTo(map);

    // A fix may already be known (e.g. cached by the sensors): place the
    // player and frame the view now instead of waiting for the next change.
    if (position.value) updatePlayer(position.value);
});

onBeforeUnmount(() => map?.remove());

// Progress can jump by more than one station (e.g. resuming a session), so
// every marker's icon is recomputed from its own index rather than just
// swapping the old/new current one.
watch(
    () => props.currentIndex,
    () => {
        childMapScene({ locations: props.locations, currentIndex: props.currentIndex }).forEach((station) => {
            markersByLocationId.get(station.id)?.setIcon(stationIcon(L, station.state));
        });
    },
);

const updatePlayer = (value) => {
    if (!value || !map) return;

    if (playerMarker) {
        playerMarker.setLatLng([value.lat, value.lng]);
    } else {
        playerMarker = L.marker([value.lat, value.lng], {
            icon: playerIcon(L, props.avatar),
            zIndexOffset: 1000,
            keyboard: false,
        }).addTo(map);

        // Frame the player and their target once, so the player's own marker
        // isn't off-screen; later panning by the player is left alone.
        const target = props.locations[props.currentIndex];
        if (target) {
            map.fitBounds([[value.lat, value.lng], [target.lat, target.lng]], FRAME_OPTIONS);
        }
    }

    trail.push([value.lat, value.lng]);
    trailLine.setLatLngs(trail);
};

watch(position, updatePlayer);
</script>

<template>
    <div class="relative h-dvh w-full">
        <div ref="mapEl" class="child-map h-full w-full"></div>
        <button
            v-if="geoError"
            type="button"
            @click="requestPermissions"
            class="absolute left-4 top-28 z-[1000] flex h-14 w-14 items-center justify-center rounded-full bg-red-500 text-2xl text-white shadow-lg"
        >
            ⚠️
        </button>
    </div>
</template>

<style>
/* Leaflet sets these as raw innerHTML on markers, outside Vue's template
   compiler, so `scoped` (which relies on compiler-added attributes) would
   never match them here — kept global and uniquely prefixed instead. */
/* Leaflet's zoom buttons, sized for small fingers (>= 56 px) and lifted
   above the home indicator, level with the map/compass toggle. */
.child-map .leaflet-bar a,
.child-map .leaflet-bar a:hover {
    width: 56px;
    height: 56px;
    line-height: 56px;
    font-size: 28px;
}

.child-map .leaflet-bottom.leaflet-left .leaflet-control-zoom {
    margin-left: 20px;
    margin-bottom: max(1.5rem, env(safe-area-inset-bottom));
}

.child-player-marker__bubble {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    border-radius: 9999px;
    background: #fff;
    font-size: 26px;
    line-height: 1;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.35);
    border: 3px solid #fff;
}

.child-station-done {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 9999px;
    background: #16a34a;
    border: 2px solid #fff;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
}

.child-station-current {
    position: relative;
    display: block;
    width: 46px;
    height: 46px;
}

.child-station-current__dot {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 22px;
    height: 22px;
    margin: -11px 0 0 -11px;
    border-radius: 9999px;
    background: #f97316;
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
}

.child-station-current__pulse {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 22px;
    height: 22px;
    margin: -11px 0 0 -11px;
    border-radius: 9999px;
    background: rgba(249, 115, 22, 0.45);
    animation: child-station-pulse 1.6s ease-out infinite;
}

@keyframes child-station-pulse {
    0% {
        transform: scale(0.7);
        opacity: 0.8;
    }
    100% {
        transform: scale(2.4);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .child-station-current__pulse {
        animation: none;
        opacity: 0.35;
    }
}

.child-station-future {
    width: 14px;
    height: 14px;
    border-radius: 9999px;
    background: #fff;
    border: 3px solid #94a3b8;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
}
</style>
