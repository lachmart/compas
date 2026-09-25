<script setup>
import { computed } from 'vue';
import FileDrop from '@/Components/UI/FileDrop.vue';
import { correctIndexAfterRemoval } from '@/lib/imageSelection';
import { fileUrl, useImageTiles } from '@/lib/useImageTiles';

const props = defineProps({
    taskType: String,
    form: Object,
    location: { type: Object, default: null },
});

// Same order the server uses for correct_index: kept images, then new uploads.
const { tiles, removeAt: removeTile, add: onImagesAdded } = useImageTiles(props.form, {
    existingKey: 'existing_images',
    newKey: 'images',
    stored: () => props.location?.images,
});

const removeAt = (index) => {
    removeTile(index);
    props.form.correct_index = correctIndexAfterRemoval(props.form.correct_index, index);
};

const total = computed(() => tiles.value.length);

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
            <p class="mt-0.5 text-xs text-slate-500">Obrázek, který hráči ukáže, co má vyfotit nebo udělat.</p>
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

    <div v-else-if="taskType === 'password'" class="space-y-2">
        <label for="location-password" class="block text-sm font-medium text-slate-700">Správné heslo</label>
        <p class="text-xs text-slate-500">
            Na velikosti písmen, diakritice ani mezerách nezáleží. Víc možných odpovědí napiš každou na nový řádek.
            Nápovědu můžeš dát do textu na stanovišti.
        </p>
        <textarea
            id="location-password"
            v-model="form.password_answers"
            rows="3"
            maxlength="1000"
            placeholder="např. žába"
            class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
        ></textarea>
        <p v-if="form.errors.password_answers" class="text-sm text-red-600">{{ form.errors.password_answers }}</p>
    </div>

    <p v-else class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500">
        Tento typ stanoviště nevyžaduje žádný obsah — splní se pouhým příchodem.
    </p>
</template>
