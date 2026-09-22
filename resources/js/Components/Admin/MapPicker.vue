<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
    modelValue: { type: Object, default: () => ({ lat: null, lng: null }) },
});
const emit = defineEmits(['update:modelValue']);

const mapEl = ref(null);
let map;
let marker;

const setPosition = (lat, lng) => {
    emit('update:modelValue', { lat, lng });

    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng]).addTo(map);
    }
};

onMounted(() => {
    const start = props.modelValue.lat
        ? [props.modelValue.lat, props.modelValue.lng]
        : [50.0755, 14.4378];

    map = L.map(mapEl.value).setView(start, 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    if (props.modelValue.lat) {
        setPosition(props.modelValue.lat, props.modelValue.lng);
    }

    map.on('click', (e) => setPosition(e.latlng.lat, e.latlng.lng));
});

onBeforeUnmount(() => map?.remove());

const useMyLocation = () => {
    navigator.geolocation.getCurrentPosition((position) => {
        const { latitude, longitude } = position.coords;
        map.setView([latitude, longitude], 18);
        setPosition(latitude, longitude);
    });
};
</script>

<template>
    <div>
        <div ref="mapEl" class="h-64 w-full rounded border"></div>
        <button type="button" @click="useMyLocation" class="mt-2 rounded bg-gray-200 px-3 py-1 text-sm">
            Použít moji aktuální polohu
        </button>
    </div>
</template>
