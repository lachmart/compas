<script setup>
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    show: { type: Boolean, required: true },
    message: { type: String, required: true },
    warning: { type: String, default: null },
    confirmLabel: { type: String, default: 'Smazat' },
    processing: { type: Boolean, default: false },
});
const emit = defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Modal :show="show" max-width="md" @close="emit('cancel')">
        <div class="space-y-4 p-6">
            <p class="text-gray-900">{{ message }}</p>
            <p v-if="warning" class="rounded bg-amber-50 p-3 text-sm text-amber-800">{{ warning }}</p>
            <div class="flex justify-end gap-3">
                <SecondaryButton type="button" @click="emit('cancel')">Zrušit</SecondaryButton>
                <DangerButton type="button" :disabled="processing" @click="emit('confirm')">{{ confirmLabel }}</DangerButton>
            </div>
        </div>
    </Modal>
</template>
