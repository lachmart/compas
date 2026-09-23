<script setup>
import { ref } from 'vue';

defineProps({
    accept: { type: String, default: 'image/*' },
    multiple: { type: Boolean, default: false },
    label: { type: String, default: 'Přetáhni sem obrázky nebo klikni pro výběr' },
});

const emit = defineEmits(['files']);

const dragOver = ref(false);

function emitFiles(fileList) {
    const files = Array.from(fileList ?? []);

    if (files.length) emit('files', files);
}

function onDrop(e) {
    dragOver.value = false;
    emitFiles(e.dataTransfer?.files);
}

function onChange(e) {
    emitFiles(e.target.files);
    e.target.value = '';
}
</script>

<template>
    <label
        class="block cursor-pointer rounded-xl border-2 border-dashed p-6 text-center text-sm text-slate-600 transition"
        :class="dragOver ? 'border-teal-500 bg-teal-50' : 'border-slate-300'"
        @dragover.prevent="dragOver = true"
        @dragleave.prevent="dragOver = false"
        @drop.prevent="onDrop"
    >
        <svg
            class="mx-auto mb-2 h-8 w-8 text-slate-400"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
            />
        </svg>
        <p>{{ label }}</p>
        <input type="file" class="hidden" :accept="accept" :multiple="multiple" @change="onChange" />
    </label>
</template>
