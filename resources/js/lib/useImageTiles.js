import { computed } from 'vue';

const objectUrls = new WeakMap();

// Preview URL of a picked (not yet uploaded) file, created once per file.
export function fileUrl(file) {
    if (!objectUrls.has(file)) objectUrls.set(file, URL.createObjectURL(file));

    return objectUrls.get(file);
}

// Image list of a location form: pictures already stored (paths in
// form[existingKey], URLs from stored()) followed by newly picked files
// (form[newKey]) — the same order the server keeps.
export function useImageTiles(form, { existingKey, newKey, stored }) {
    const storedUrls = computed(() => Object.fromEntries((stored() ?? []).map((image) => [image.path, image.url])));

    const tiles = computed(() => [
        ...form[existingKey].map((path) => ({ key: `existing:${path}`, src: storedUrls.value[path] })),
        ...form[newKey].map((file, i) => ({ key: `new:${i}:${file.name}`, src: fileUrl(file) })),
    ]);

    const removeAt = (index) => {
        const keptCount = form[existingKey].length;

        if (index < keptCount) {
            form[existingKey] = form[existingKey].filter((_, i) => i !== index);
        } else {
            form[newKey] = form[newKey].filter((_, i) => i !== index - keptCount);
        }
    };

    const add = (files, max = Infinity) => {
        form[newKey] = [...form[newKey], ...files].slice(0, max - form[existingKey].length);
    };

    return { tiles, removeAt, add };
}
