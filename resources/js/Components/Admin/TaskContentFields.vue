<script setup>
const props = defineProps({
    taskType: String,
    form: Object,
});

const onImagesChange = (event) => {
    props.form.images = Array.from(event.target.files);
};

const onReferenceImageChange = (event) => {
    props.form.reference_image = event.target.files[0];
};

const objectUrl = (file) => URL.createObjectURL(file);
</script>

<template>
    <div v-if="taskType === 'pick_image'" class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">Obrázky (3–6), vyber správný</label>
        <input type="file" accept="image/*" multiple @change="onImagesChange" />
        <div v-if="form.images?.length" class="flex flex-wrap gap-2">
            <label v-for="(image, index) in form.images" :key="index" class="flex flex-col items-center gap-1">
                <img :src="objectUrl(image)" class="h-20 w-20 rounded object-cover" />
                <input type="radio" :value="index" v-model.number="form.correct_index" />
            </label>
        </div>
        <p v-if="form.errors.images" class="text-sm text-red-600">{{ form.errors.images }}</p>
        <p v-if="form.errors.correct_index" class="text-sm text-red-600">{{ form.errors.correct_index }}</p>
    </div>

    <div v-else-if="taskType === 'photo_of_something' || taskType === 'selfie_doing_something'" class="space-y-2">
        <label class="block text-sm font-medium text-gray-700">Referenční obrázek (ukazuje, co má dítě udělat)</label>
        <input type="file" accept="image/*" @change="onReferenceImageChange" />
        <p v-if="form.errors.reference_image" class="text-sm text-red-600">{{ form.errors.reference_image }}</p>
    </div>

    <p v-else class="text-sm text-gray-500">Tento typ stanoviště nevyžaduje žádný obsah — splní se pouhým příchodem.</p>
</template>
