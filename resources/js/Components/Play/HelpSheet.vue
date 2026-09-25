<script setup>
import { computed } from 'vue';
import { avatarEmoji } from '@/lib/avatars';

// "How to play": opens by itself before the first game and later from the
// player's animal in the HUD.
const props = defineProps({
    avatar: { type: String, default: null },
});
const emit = defineEmits(['close']);

const steps = computed(() => [
    { icon: '\u{1F9ED}', title: 'Jdi za šipkou', text: 'Šipka ukazuje směr k dalšímu stanovišti. Drž telefon naplocho před sebou.' },
    { icon: '\u{1F321}\u{FE0F}', title: 'Přihořívá, hoří', text: 'Čím víc dílků teploměru svítí a čím teplejší je barva, tím jsi blíž. Číslo je vzdálenost v metrech.' },
    { icon: '\u{1F4F7}', title: 'Zaměř se kamerou', text: 'Když telefon zvedneš svisle, uvidíš cíl v obraze. Otoč se, aby byl terč uprostřed zaměřovače.' },
    { icon: '\u{23F3}', title: 'Na místě chvilku počkej', text: 'Až dojdeš, zůstaň stát, dokud se kolem nenaplní kruh.' },
    { icon: '\u{2B50}', title: 'Splň úkol', text: 'Vyber obrázek, vyfoť, co je potřeba, nebo napiš heslo. Za každé stanoviště dostaneš hvězdu.' },
    { icon: '\u{1F5FA}\u{FE0F}', title: 'Mapa', text: 'Tlačítko vpravo dole přepíná mezi kompasem a mapou.' },
    { icon: '\u{1F4D6}', title: 'Deník', text: 'Tlačítko vlevo dole ukáže texty a tvoje fotky z prošlých stanovišť.' },
    { icon: avatarEmoji(props.avatar), title: 'Nápověda', text: 'Tuhle nápovědu kdykoli otevřeš klepnutím na své zvířátko.' },
]);
</script>

<template>
    <div class="fixed inset-0 z-[1300] flex items-end justify-center bg-slate-900/40 p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="help-title">
        <div class="max-h-full w-full max-w-md overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <h2 id="help-title" class="text-2xl font-bold text-teal-900">Jak hrát</h2>
                <button
                    type="button"
                    aria-label="Zavřít"
                    class="-mr-2 -mt-2 flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100"
                    @click="emit('close')"
                >
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                        <path d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>

            <ol class="mt-4 space-y-4">
                <li v-for="step in steps" :key="step.title" class="flex gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-teal-50 text-2xl" aria-hidden="true">{{ step.icon }}</span>
                    <div>
                        <p class="font-semibold text-slate-900">{{ step.title }}</p>
                        <p class="text-sm text-slate-600">{{ step.text }}</p>
                    </div>
                </li>
            </ol>

            <button
                type="button"
                class="mt-6 w-full rounded-full bg-gradient-to-b from-emerald-400 to-emerald-600 px-6 py-4 text-lg font-bold text-white shadow-lg shadow-emerald-700/30 active:scale-[0.98]"
                @click="emit('close')"
            >Rozumím, jdeme na to</button>
        </div>
    </div>
</template>
