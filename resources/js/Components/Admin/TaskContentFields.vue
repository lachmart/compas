<script setup>
import { computed } from 'vue';
import FileDrop from '@/Components/UI/FileDrop.vue';
import { correctIndexAfterRemoval } from '@/lib/imageSelection';

const props = defineProps({
    taskType: String,
    form: Object,
    location: { type: Object, default: null },
});

const existingUrls = computed(() =>
    Object.fromEntries((props.location?.images ?? []).map((image) => [image.path, image.url])),
);

const objectUrls = new WeakMap();
const fileUrl = (file) => {
    if (!objectUrls.has(file)) objectUrls.set(file, URL.createObjectURL(file));

    return objectUrls.get(file);
};

const removeAt = (index) => {
    const keptCount = props.form.existing_images.length;

    if (index < keptCount) {
        props.form.existing_images = props.form.existing_images.filter((_, i) => i !== index);
    } else {
        props.form.images = props.form.images.filter((_, i) => i !== index - keptCount);
    }

    props.form.correct_index = correctIndexAfterRemoval(props.form.correct_index, index);
};

// Same order the server uses for correct_index: kept images, then new uploads.
const tiles = computed(() => [
    ...props.form.existing_images.map((path) => ({ key: `existing:${path}`, src: existingUrls.value[path] })),
    ...props.form.images.map((file, i) => ({ key: `new:${i}:${file.name}`, src: fileUrl(file) })),
]);

const total = computed(() => tiles.value.length);

const onImagesAdded = (files) => {
    props.form.images = [...props.form.images, ...files];
};

const currentReference = computed(() => props.location?.reference_image?.url ?? null);

const onReferenceImageChange = (files) => {
    props.form.reference_image = files[0] ?? null;
};
</script>

<template>
    <div v-if="taskType === 'pick_image'" class="space-y-3">
        <div>
            <p class="text-sm font-medium text-slate-700">Obrázky na výběr</p>
            <p class="mt-0.5 text-xs text-slate-500">Nahraj 3–6 obrázků a klikni na ten správný.</p>
        </div>

        <div v-if="tiles.length" class="flex flex-wrap gap-3" role="radiogroup" aria-label="Správný obrázek">
            <div v-for="(tile, index) in tiles" :key="tile.key" class="relative">
                <button
                    type="button"
                    role="radio"
                    :aria-checked="form.correct_index === index"
                    :aria-label="`Obrázek ${index + 1}`"
                    class="block h-24 w-24 overflow-hidden rounded-xl bg-slate-100 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2"
                    :class="form.correct_index === index ? 'ring-2 ring-green-500 ring-offset-2' : 'ring-1 ring-slate-200 hover:ring-slate-400'"
                    title="Označit jako správný"
                    @click="form.correct_index = index"
                >
                    <img :src="tile.src" alt="" class="h-full w-full object-cover" />
                </button>
                <span
                    v-if="form.correct_index === index"
                    class="pointer-events-none absolute bottom-1.5 left-1/2 -translate-x-1/2 rounded-full bg-green-600 px-2 py-0.5 text-[11px] font-semibold text-white shadow"
                >správný</span>
                <button
                    type="button"
                    title="Odebrat obrázek"
                    aria-label="Odebrat obrázek"
                    class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-white text-xs text-slate-500 shadow ring-1 ring-slate-900/10 transition hover:bg-red-50 hover:text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
                    @click="removeAt(index)"
                >✕</button>
            </div>
        </div>

        <p class="text-xs" :class="total >= 3 && total <= 6 ? 'text-slate-500' : 'text-amber-700'">
            Obrázky: {{ total }} (potřeba 3–6)<template v-if="total && form.correct_index === null"> · ještě označ správný</template>
        </p>

        <FileDrop v-if="total < 6" multiple label="Přetáhni sem obrázky nebo klikni pro výběr" @files="onImagesAdded" />

        <p v-if="form.errors.images" class="text-sm text-red-600">{{ form.errors.images }}</p>
        <p v-if="form.errors.correct_index" class="text-sm text-red-600">{{ form.errors.correct_index }}</p>
        <p v-if="Object.keys(form.errors).some((key) => key.startsWith('existing_images'))" class="text-sm text-red-600">
            Některý ze stávajících obrázků už neexistuje — obnov stránku.
        </p>
    </div>

    <div v-else-if="taskType === 'photo_of_something' || taskType === 'selfie_doing_something'" class="space-y-3">
        <div>
            <p class="text-sm font-medium text-slate-700">Předloha</p>
            <p class="mt-0.5 text-xs text-slate-500">Obrázek, který dítěti ukáže, co má vyfotit nebo udělat.</p>
        </div>

        <div v-if="form.reference_image || currentReference" class="flex items-start gap-3">
            <img
                :src="form.reference_image ? fileUrl(form.reference_image) : currentReference"
                alt=""
                class="h-32 w-32 rounded-xl object-cover ring-1 ring-slate-200"
            />
            <div class="space-y-2 pt-1 text-xs text-slate-500">
                <p v-if="form.reference_image">Nový obrázek — uloží se se stanovištěm.</p>
                <p v-else>Bez výběru nového souboru zůstane současný obrázek.</p>
                <button
                    v-if="form.reference_image"
                    type="button"
                    class="font-medium text-slate-600 underline-offset-2 hover:text-red-600 hover:underline"
                    @click="form.reference_image = null"
                >{{ currentReference ? 'Vrátit původní' : 'Odebrat' }}</button>
            </div>
        </div>

        <FileDrop
            :label="form.reference_image || currentReference ? 'Vyměnit obrázek — přetáhni sem nebo klikni' : 'Přetáhni sem obrázek nebo klikni pro výběr'"
            @files="onReferenceImageChange"
        />
        <p v-if="form.errors.reference_image" class="text-sm text-red-600">{{ form.errors.reference_image }}</p>
    </div>

    <p v-else class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">
        Tento typ stanoviště nevyžaduje žádný obsah — splní se pouhým příchodem.
    </p>
</template>
