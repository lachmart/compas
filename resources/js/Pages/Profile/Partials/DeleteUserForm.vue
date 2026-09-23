<script setup>
import UIButton from '@/Components/UI/Button.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-medium text-gray-900"> Smazání účtu </h2>

            <p class="mt-1 text-sm text-gray-600">
                Smazáním účtu se trvale smažou i všechny vaše hry a jejich data. Před smazáním si stáhněte vše, co si chcete ponechat.
            </p>
        </header>

        <UIButton variant="danger" @click="confirmUserDeletion">Smazat účet</UIButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900"
                >
                    Opravdu chcete smazat svůj účet?
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Smazáním účtu se trvale smažou i všechny vaše hry a jejich data. Pro potvrzení zadejte své heslo.
                </p>

                <div class="mt-6">
                    <InputLabel
                        for="password"
                        value="Heslo"
                        class="sr-only"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-3/4"
                        placeholder="Heslo"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <UIButton variant="secondary" @click="closeModal"> Zrušit </UIButton>

                    <UIButton
                        variant="danger"
                        :processing="form.processing"
                        @click="deleteUser"
                    > Smazat účet </UIButton>
                </div>
            </div>
        </Modal>
    </section>
</template>
