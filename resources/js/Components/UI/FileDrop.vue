<script setup>
import { ref } from 'vue';

const props = defineProps({
    accept: { type: String, default: 'image/*' },
    multiple: { type: Boolean, default: false },
    label: { type: String, default: 'Přetáhni sem obrázky nebo klikni pro výběr' },
});

const emit = defineEmits(['files']);

const dragOver = ref(false);
let dragDepth = 0;

function emitFiles(fileList) {
    const files = Array.from(fileList ?? []);

    if (files.length) emit('files', files);
}

// dragenter/dragleave also fire when the pointer crosses child elements
// (the icon, the text) inside the drop zone, so a plain dragleave handler
// flickers. A depth counter only clears the state once the pointer has
// really left the label.
function onDragEnter() {
    dragDepth += 1;
    dragOver.value = true;
}

function onDragLeave() {
    dragDepth = Math.max(0, dragDepth - 1);

    if (dragDepth === 0) dragOver.value = false;
}

// The file picker enforces `accept`/`multiple`; a drop does not, so apply the
// same rules by hand (`image/*`, `image/png`, `.jpg` style tokens).
function matchesAccept(file) {
    const tokens = props.accept.split(',').map((t) => t.trim().toLowerCase()).filter(Boolean);
    if (!tokens.length) return true;

    const type = (file.type || '').toLowerCase();
    const name = (file.name || '').toLowerCase();

    return tokens.some((token) => {
        if (token.startsWith('.')) return name.endsWith(token);
        if (token.endsWith('/*')) return type.startsWith(token.slice(0, -1));

        return type === token;
    });
}

function onDrop(e) {
    dragDepth = 0;
    dragOver.value = false;

    const files = Array.from(e.dataTransfer?.files ?? []).filter(matchesAccept);
    emitFiles(props.multiple ? files : files.slice(0, 1));
}

function onChange(e) {
    emitFiles(e.target.files);
    e.target.value = '';
}
</script>

<template>
    <label
        class="block cursor-pointer rounded-xl border-2 border-dashed p-6 text-center text-sm text-slate-600 transition focus-within:ring-2 focus-within:ring-teal-500 focus-within:ring-offset-2"
        :class="dragOver ? 'border-teal-500 bg-teal-50' : 'border-slate-300'"
        @dragenter.prevent="onDragEnter"
        @dragover.prevent
        @dragleave.prevent="onDragLeave"
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
        <input type="file" class="sr-only" :accept="accept" :multiple="multiple" @change="onChange" />
    </label>
</template>
