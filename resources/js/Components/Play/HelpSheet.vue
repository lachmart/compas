<script setup>
import PlaySheet from '@/Components/Play/PlaySheet.vue';
import { computed, ref } from 'vue';
import { avatarEmoji } from '@/lib/avatars';
import { useCameraPreference } from '@/lib/useCameraPreference';

// "How to play": opens by itself before the first game and later from the
// player's animal in the HUD.
const props = defineProps({
    avatar: { type: String, default: null },
});
const emit = defineEmits(['close']);

// Change of mind about the camera (AR aim): switch it on or off here.
const { preference: cameraChoice, enableCamera, disableCamera } = useCameraPreference();
const cameraOn = computed(() => cameraChoice.value !== 'declined');
const cameraBusy = ref(false);
const cameraBlocked = ref(false);

const turnCameraOn = async () => {
    // Called straight from the tap, so the browser may show its prompt.
    cameraBusy.value = true;
    cameraBlocked.value = false;
    const result = await enableCamera();
    cameraBusy.value = false;
    cameraBlocked.value = result !== 'granted';
};

const turnCameraOff = () => {
    cameraBlocked.value = false;
    disableCamera();
};

const steps = computed(() => [
    { icon: '\u{1F9ED}', title: 'Jdi za šipkou', text: 'Šipka ukazuje směr k dalšímu stanovišti. Drž telefon naplocho před sebou.' },
    { icon: '\u{1F321}\u{FE0F}', title: 'Přihořívá, hoří', text: 'Čím víc dílků teploměru svítí a čím teplejší je barva, tím jsi blíž. Číslo je vzdálenost v metrech.' },
    { icon: '\u{1F4F7}', title: 'Zaměř se kamerou', text: 'Když telefon zvedneš svisle, uvidíš cíl v obraze. Otoč se, aby byl terč uprostřed zaměřovače.' },
    { icon: '\u{23F3}', title: 'Na místě chvilku počkej', text: 'Až dojdeš, zůstaň chvíli stát, než se kruh kolem šipky celý vybarví.' },
    { icon: '\u{2B50}', title: 'Splň úkol', text: 'Vyber obrázek, vyfoť, co je potřeba, nebo napiš heslo. Za každé stanoviště dostaneš hvězdu.' },
    { icon: '\u{1F5FA}\u{FE0F}', title: 'Mapa', text: 'Tlačítko vpravo dole přepíná mezi kompasem a mapou.' },
    { icon: '\u{1F4D6}', title: 'Deník', text: 'Tlačítko vlevo dole ukáže texty a tvoje fotky z prošlých stanovišť.' },
    { icon: avatarEmoji(props.avatar), title: 'Nápověda', text: 'Tuhle nápovědu kdykoli otevřeš klepnutím na své zvířátko.' },
]);
</script>

<template>
    <PlaySheet title="Jak hrát" @close="emit('close')">

        <ol class="mt-4 space-y-4">
            <li v-for="step in steps" :key="step.title" class="flex gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-teal-50 text-2xl" aria-hidden="true">{{ step.icon }}</span>
                <div>
                    <p class="font-semibold text-slate-900">{{ step.title }}</p>
                    <p class="text-sm text-slate-600">{{ step.text }}</p>
                </div>
            </li>
        </ol>

        <div class="mt-6 rounded-2xl bg-slate-50 p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="font-semibold text-slate-900">Fotoaparát pro zaměřování</p>
                    <p class="text-sm text-slate-600">{{ cameraOn ? 'Zapnutý' : 'Vypnutý' }} · nepovinný</p>
                </div>
                <button
                    v-if="cameraOn"
                    type="button"
                    class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-300 active:scale-95"
                    @click="turnCameraOff"
                >Vypnout</button>
                <button
                    v-else
                    type="button"
                    :disabled="cameraBusy"
                    class="shrink-0 rounded-full bg-teal-600 px-4 py-2 text-sm font-semibold text-white active:scale-95 disabled:opacity-60"
                    @click="turnCameraOn"
                >Zapnout</button>
            </div>
            <p v-if="cameraBlocked" class="mt-3 text-sm text-amber-800" role="alert">
                Prohlížeč přístup ke kameře nepovolil. Povol fotoaparát v nastavení prohlížeče pro tuto stránku a zkus to znovu.
            </p>
        </div>

        <button
            type="button"
            class="mt-6 w-full rounded-full bg-gradient-to-b from-emerald-400 to-emerald-600 px-6 py-4 text-lg font-bold text-white shadow-lg shadow-emerald-700/30 active:scale-[0.98]"
            @click="emit('close')"
        >Rozumím, jdeme na to</button>
    </PlaySheet>
</template>
