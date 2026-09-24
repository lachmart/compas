<script setup>
import { computed, ref } from 'vue';
import FileDrop from '@/Components/UI/FileDrop.vue';

// The optional text board a player reads on arrival (nature-trail style).
const props = defineProps({
    form: { type: Object, required: true },
    location: { type: Object, default: null },
});

const MAX_IMAGES = 6;

// Collapsed for stations without a board, so the form stays short.
const open = ref(Boolean(props.form.info_title || props.form.info_text || props.form.existing_info_images.length));

const existingUrls = computed(() =>
    Object.fromEntries((props.location?.info_images ?? []).map((image) => [image.path, image.url])),
);

const objectUrls = new WeakMap();
const fileUrl = (file) => {
    if (!objectUrls.has(file)) objectUrls.set(file, URL.createObjectURL(file));

    return objectUrls.get(file);
};

// Same order the server keeps: kept pictures, then new uploads.
const tiles = computed(() => [
    ...props.form.existing_info_images.map((path) => ({ key: `existing:${path}`, src: existingUrls.value[path] })),
    ...props.form.info_images.map((file, i) => ({ key: `new:${i}:${file.name}`, src: fileUrl(file) })),
]);

const removeAt = (index) => {
    const keptCount = props.form.existing_info_images.length;

    if (index < keptCount) {
        props.form.existing_info_images = props.form.existing_info_images.filter((_, i) => i !== index);
    } else {
        props.form.info_images = props.form.info_images.filter((_, i) => i !== index - keptCount);
    }
};

const onImagesAdded = (files) => {
    props.form.info_images = [...props.form.info_images, ...files].slice(0, MAX_IMAGES - props.form.existing_info_images.length);
};

const hasImageError = computed(() => Object.keys(props.form.errors).some((key) => key.startsWith('info_images') || key.startsWith('existing_info_images')));

const inputClasses = 'block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500';
</script>

<template>
    <section class="rounded-xl ring-1 ring-slate-200">
        <button
            type="button"
            class="flex w-full items-start justify-between gap-3 rounded-xl p-4 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span>
                <span class="block text-sm font-medium text-slate-700">Text na stanovišti</span>
                <span class="mt-0.5 block text-xs text-slate-500">Nepovinné — hráč si ho přečte hned po příchodu, ještě před úkolem. Jako tabule naučné stezky.</span>
            </span>
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-400 transition" :class="{ 'rotate-180': open }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </button>

        <div v-if="open" class="space-y-4 border-t border-slate-200 p-4">
            <div>
                <label for="info-title" class="block text-sm font-medium text-slate-700">Nadpis</label>
                <input id="info-title" v-model="form.info_title" type="text" maxlength="150" placeholder="např. Starý dub" class="mt-1" :class="inputClasses" />
                <p v-if="form.errors.info_title" class="mt-1 text-sm text-red-600">{{ form.errors.info_title }}</p>
            </div>

            <div>
                <label for="info-text" class="block text-sm font-medium text-slate-700">Text</label>
                <p class="mt-0.5 text-xs text-slate-500">Odstavce odděl prázdným řádkem.</p>
                <textarea id="info-text" v-model="form.info_text" rows="6" maxlength="10000" class="mt-1" :class="inputClasses"></textarea>
                <p v-if="form.errors.info_text" class="mt-1 text-sm text-red-600">{{ form.errors.info_text }}</p>
            </div>

            <div class="space-y-3">
                <p class="text-sm font-medium text-slate-700">Obrázky <span class="font-normal text-slate-500">(nejvýš {{ MAX_IMAGES }})</span></p>

                <div v-if="tiles.length" class="flex flex-wrap gap-3">
                    <div v-for="(tile, index) in tiles" :key="tile.key" class="relative">
                        <img :src="tile.src" alt="" class="h-24 w-24 rounded-xl object-cover ring-1 ring-slate-200" />
                        <button
                            type="button"
                            title="Odebrat obrázek"
                            aria-label="Odebrat obrázek"
                            class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-white text-xs text-slate-500 shadow ring-1 ring-slate-900/10 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
                            @click="removeAt(index)"
                        >✕</button>
                    </div>
                </div>

                <FileDrop v-if="tiles.length < MAX_IMAGES" multiple label="Přetáhni sem obrázky nebo klikni pro výběr" @files="onImagesAdded" />

                <p v-if="form.errors.info_images" class="text-sm text-red-600">{{ form.errors.info_images }}</p>
                <p v-else-if="hasImageError" class="text-sm text-red-600">Některý obrázek se nepodařilo uložit — zkontroluj, že jde o obrázek do 5 MB, a obnov stránku.</p>
            </div>
        </div>
    </section>
</template>
