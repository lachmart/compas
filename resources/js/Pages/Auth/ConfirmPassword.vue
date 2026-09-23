<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Potvrzení hesla" />

        <h1 class="mb-4 text-2xl font-bold text-gray-900">Potvrzení hesla</h1>

        <div class="mb-4 text-sm text-gray-600">
            Toto je zabezpečená část aplikace. Než budete pokračovat, potvrďte prosím své heslo.
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="password" value="Heslo" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 flex justify-end">
                <AuthButton :processing="form.processing">Potvrdit</AuthButton>
            </div>
        </form>
    </GuestLayout>
</template>
