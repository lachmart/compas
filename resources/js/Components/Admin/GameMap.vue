<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { mapScene, numberedIcon, ORANGE, TEAL } from '@/lib/mapMarkers';

const props = defineProps({
    // [{ id, lat, lng, arrival_radius_m }] in game order.
    locations: { type: Array, required: true },
    selectedId: { type: Number, default: null },
    // { lat, lng, arrival_radius_m } of the location being edited / added.
    draft: { type: Object, default: null },
    mode: {
        type: String,
        default: 'view',
        validator: (value) => ['view', 'edit', 'place'].includes(value),
    },
    showRadii: { type: Boolean, default: true },
});
const emit = defineEmits(['select', 'update:draft']);

const CZ_CENTER = [49.8, 15.5];
const FIT_OPTIONS = { padding: [60, 60], maxZoom: 17 };

const mapEl = ref(null);
let map = null;
let locationLayer = null;
let draftLayer = null;
// The draft's radius circle, kept so the radius slider can resize it in place.
let draftCircle = null;
let resizeObserver = null;
let isMounted = false;
// Set on the first real user input on the map (drag, wheel, pinch, zoom
// buttons, keyboard). Programmatic view changes also fire Leaflet's
// movestart/zoomstart, so we listen to raw DOM input instead.
let userInteracted = false;
const USER_INPUT_EVENTS = ['pointerdown', 'wheel', 'keydown', 'touchstart'];
const markInteracted = () => { userInteracted = true; };

const scene = computed(() => mapScene({
    locations: props.locations,
    selectedId: props.selectedId,
    draft: props.draft,
    mode: props.mode,
}));

const picking = computed(() => props.mode === 'edit' || props.mode === 'place');

const radiusCircle = (lat, lng, radius, color) => L.circle([lat, lng], {
    radius,
    color,
    weight: 1.5,
    fillColor: color,
    fillOpacity: 0.12,
    // Clicks pass through to the map (so a click inside a circle still places the draft).
    interactive: false,
});

const drawLocations = () => {
    if (!locationLayer) return;
    locationLayer.clearLayers();

    const { markers, route } = scene.value;

    if (route.length > 1) {
        L.polyline(route, { color: TEAL, weight: 3, opacity: 0.8, dashArray: '6 8', interactive: false })
            .addTo(locationLayer);
    }

    markers.forEach((m) => {
        if (props.showRadii && m.radius) {
            radiusCircle(m.lat, m.lng, m.radius, m.selected ? ORANGE : TEAL).addTo(locationLayer);
        }

        L.marker([m.lat, m.lng], {
            icon: numberedIcon(L, m.number, { selected: m.selected }),
            zIndexOffset: m.selected ? 500 : 0,
            keyboard: false,
        })
            .on('click', () => emit('select', m.id))
            .addTo(locationLayer);
    });
};

const drawDraft = () => {
    if (!draftLayer) return;
    draftLayer.clearLayers();
    draftCircle = null;

    const draft = scene.value.draft;
    if (!draft) return;

    const circle = draft.radius ? radiusCircle(draft.lat, draft.lng, draft.radius, ORANGE).addTo(draftLayer) : null;
    draftCircle = circle;

    L.marker([draft.lat, draft.lng], {
        icon: numberedIcon(L, draft.number, { selected: true }),
        draggable: true,
        autoPan: true,
        zIndexOffset: 1000,
    })
        // Keep the circle under the marker while dragging; the parent only hears about the final position.
        .on('drag', (e) => circle?.setLatLng(e.target.getLatLng()))
        .on('dragend', (e) => {
            const { lat, lng } = e.target.getLatLng();
            emit('update:draft', { lat, lng });
        })
        .addTo(draftLayer);
};

const redraw = () => {
    drawLocations();
    drawDraft();
};

// The radius slider fires on every input event: resize the existing circle
// instead of rebuilding every layer.
const updateDraftRadius = () => {
    const radius = scene.value.draft?.radius;
    if (draftCircle && radius) draftCircle.setRadius(radius);
    else drawDraft();
};

const updateCursor = () => {
    if (!map) return;
    // Inline style so it wins over Leaflet's grab cursor. Never bind :class on
    // the map element — Vue would overwrite Leaflet's own classes.
    map.getContainer().style.cursor = picking.value ? 'crosshair' : '';
};

const onMapClick = (e) => {
    if (!picking.value) return;
    emit('update:draft', { lat: e.latlng.lat, lng: e.latlng.lng });
};

const fitAll = () => {
    if (!map) return;

    const points = props.locations.map((l) => [l.lat, l.lng]);
    if (scene.value.draft) points.push([scene.value.draft.lat, scene.value.draft.lng]);

    if (points.length) map.fitBounds(points, FIT_OPTIONS);
};

const flyTo = (lat, lng) => {
    if (!map) return;
    map.flyTo([lat, lng], Math.max(map.getZoom(), 16));
};

const setInitialView = () => {
    if (props.locations.length) {
        map.fitBounds(props.locations.map((l) => [l.lat, l.lng]), FIT_OPTIONS);
        return;
    }

    map.setView(CZ_CENTER, 7);

    // One-shot: centre on the device if it tells us where it is.
    navigator.geolocation?.getCurrentPosition(
        ({ coords }) => {
            // Don't yank the map away from where the dozor already is or is working.
            if (!isMounted || !map || userInteracted) return;
            if (props.locations.length || props.draft?.lat != null) return;
            map.setView([coords.latitude, coords.longitude], 16);
        },
        () => {},
        { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 },
    );
};

onMounted(() => {
    isMounted = true;

    map = L.map(mapEl.value);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);

    locationLayer = L.layerGroup().addTo(map);
    draftLayer = L.layerGroup().addTo(map);

    map.on('click', onMapClick);
    USER_INPUT_EVENTS.forEach((type) => mapEl.value.addEventListener(type, markInteracted, { passive: true }));

    setInitialView();
    redraw();
    updateCursor();

    // The editor layout resizes the map (mobile ↔ desktop, panel changes).
    resizeObserver = new ResizeObserver(() => map?.invalidateSize());
    resizeObserver.observe(mapEl.value);
});

onBeforeUnmount(() => {
    isMounted = false;
    resizeObserver?.disconnect();
    resizeObserver = null;
    USER_INPUT_EVENTS.forEach((type) => mapEl.value?.removeEventListener(type, markInteracted));
    map?.remove();
    map = null;
    locationLayer = null;
    draftLayer = null;
    draftCircle = null;
});

// Everything but the draft radius needs a full redraw.
watch(
    [
        () => props.locations,
        () => props.selectedId,
        () => props.mode,
        () => props.draft === null,
        () => props.draft?.lat,
        () => props.draft?.lng,
    ],
    redraw,
);
watch(() => props.draft?.arrival_radius_m, updateDraftRadius);
watch(() => props.showRadii, drawLocations);
watch(picking, updateCursor);

defineExpose({ fitAll, flyTo });
</script>

<template>
    <div ref="mapEl" class="h-full w-full"></div>
</template>
