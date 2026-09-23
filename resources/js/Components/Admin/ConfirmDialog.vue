<script setup>
import Modal from '@/Components/Modal.vue';
import Button from '@/Components/UI/Button.vue';

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
            <p class="text-slate-900">{{ message }}</p>
            <p v-if="warning" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{{ warning }}</p>
            <div class="flex justify-end gap-3">
                <Button variant="secondary" type="button" @click="emit('cancel')">Zrušit</Button>
                <Button variant="danger" type="button" :processing="processing" @click="emit('confirm')">
                    {{ confirmLabel }}
                </Button>
            </div>
        </div>
    </Modal>
</template>
