<script setup>
import { computed } from 'vue';
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

const onImagesAdded = (event) => {
    props.form.images = [...props.form.images, ...Array.from(event.target.files)];
    event.target.value = '';
};

const currentReference = computed(() => props.location?.reference_image?.url ?? null);

const onReferenceImageChange = (event) => {
    props.form.reference_image = event.target.files[0] ?? null;
};
</script>

<template>
    <div v-if="taskType === 'pick_image'" class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">Obrázky (3–6) — označ správný</label>

        <div class="flex flex-wrap gap-3">
            <div
                v-for="(tile, index) in tiles"
                :key="tile.key"
                class="relative flex flex-col items-center gap-1 rounded border p-1"
                :class="form.correct_index === index ? 'border-green-500 ring-2 ring-green-400' : 'border-gray-200'"
            >
                <img :src="tile.src" class="h-20 w-20 rounded object-cover" />
                <button
                    type="button"
                    title="Odebrat obrázek"
                    class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-white text-sm text-red-600 shadow"
                    @click="removeAt(index)"
                >✕</button>
                <label class="flex items-center gap-1 text-xs text-gray-700">
                    <input v-model.number="form.correct_index" type="radio" :value="index" />
                    správný
                </label>
            </div>
        </div>

        <p class="text-sm" :class="total >= 3 && total <= 6 ? 'text-gray-500' : 'text-amber-700'">
            Obrázky: {{ total }} (potřeba 3–6)
        </p>

        <label v-if="total < 6" class="inline-block cursor-pointer rounded bg-gray-100 px-3 py-1 text-sm text-gray-800 hover:bg-gray-200">
            Přidat obrázky
            <input type="file" accept="image/*" multiple class="hidden" @change="onImagesAdded" />
        </label>

        <p v-if="form.errors.images" class="text-sm text-red-600">{{ form.errors.images }}</p>
        <p v-if="form.errors.correct_index" class="text-sm text-red-600">{{ form.errors.correct_index }}</p>
        <p v-if="Object.keys(form.errors).some((key) => key.startsWith('existing_images'))" class="text-sm text-red-600">
            Některý ze stávajících obrázků už neexistuje — obnov stránku.
        </p>
    </div>

    <div v-else-if="taskType === 'photo_of_something' || taskType === 'selfie_doing_something'" class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">Referenční obrázek (ukazuje, co má dítě udělat)</label>
        <img v-if="form.reference_image" :src="fileUrl(form.reference_image)" class="h-32 rounded object-cover" />
        <img v-else-if="currentReference" :src="currentReference" class="h-32 rounded object-cover" />
        <input type="file" accept="image/*" @change="onReferenceImageChange" />
        <p v-if="currentReference && !form.reference_image" class="text-xs text-gray-500">
            Bez výběru nového souboru zůstane současný obrázek.
        </p>
        <p v-if="form.errors.reference_image" class="text-sm text-red-600">{{ form.errors.reference_image }}</p>
    </div>

    <p v-else class="text-sm text-gray-500">Tento typ stanoviště nevyžaduje žádný obsah — splní se pouhým příchodem.</p>
</template>
