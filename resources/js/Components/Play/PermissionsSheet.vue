<script setup>
import PlaySheet from '@/Components/Play/PlaySheet.vue';
// Explains, before the browser asks, which permissions the game needs and
// why — so no prompt pops up unexpectedly in the middle of the game.
const emit = defineEmits(['start', 'close']);

// Emitted synchronously from the tap, so the parent can still ask for the
// permissions inside the user gesture (iOS rule).
const start = (withCamera) => emit('start', withCamera);
</script>

<template>
    <PlaySheet title="Než vyrazíš" close-label="Zpět" @close="emit('close')">
        <p class="mt-1 text-slate-600">Telefon se tě teď zeptá na pár povolení. Tady je proč:</p>

        <ul class="mt-5 space-y-4">
            <li class="flex gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-teal-100 text-teal-700" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                        <path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21Z" />
                        <circle cx="12" cy="9.5" r="2.5" />
                    </svg>
                </span>
                <div>
                    <p class="font-semibold text-slate-900">Poloha <span class="text-sm font-medium text-teal-700">· nutná</span></p>
                    <p class="text-sm text-slate-600">Podle ní hra pozná, kde jsi a kdy dojdeš na stanoviště.</p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-teal-100 text-teal-700" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="9" />
                        <path d="M15.5 8.5 13.4 13.4 8.5 15.5 10.6 10.6Z" />
                    </svg>
                </span>
                <div>
                    <p class="font-semibold text-slate-900">Kompas <span class="text-sm font-medium text-teal-700">· nutný</span></p>
                    <p class="text-sm text-slate-600">Díky němu ti šipka ukazuje, kterým směrem jít. Telefon se může ptát na „pohyb a orientaci“.</p>
                </div>
            </li>
            <li class="flex gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-600" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round">
                        <path d="M4 8.5A1.5 1.5 0 0 1 5.5 7h2l1.5-2h6l1.5 2h2A1.5 1.5 0 0 1 20 8.5v9a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 17.5Z" />
                        <circle cx="12" cy="13" r="3.5" />
                    </svg>
                </span>
                <div>
                    <p class="font-semibold text-slate-900">Fotoaparát <span class="text-sm font-medium text-slate-500">· nepovinný</span></p>
                    <p class="text-sm text-slate-600">
                        Když telefon zvedneš, uvidíš cíl přímo v obraze z kamery. Hrát jde i bez něj.
                        U fotoúkolů se fotoaparát telefonu otevře až ve chvíli, kdy budeš fotit.
                    </p>
                </div>
            </li>
        </ul>

        <div class="mt-6 flex flex-col gap-2">
            <button
                type="button"
                class="rounded-full bg-gradient-to-b from-emerald-400 to-emerald-600 px-6 py-4 text-lg font-bold text-white shadow-lg shadow-emerald-700/30 active:scale-[0.98]"
                @click="start(true)"
            >Povolit a pokračovat</button>
            <button
                type="button"
                class="rounded-full px-6 py-3 font-semibold text-teal-800 hover:bg-teal-50 active:scale-[0.98]"
                @click="start(false)"
            >Pokračovat bez fotoaparátu</button>
        </div>
    </PlaySheet>
</template>
