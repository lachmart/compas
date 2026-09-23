<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Zapomenuté heslo" />

        <h1 class="mb-4 text-2xl font-bold text-gray-900">Zapomenuté heslo</h1>

        <div class="mb-4 text-sm text-gray-600">
            Zapomněli jste heslo? Nevadí. Zadejte svůj e-mail a pošleme vám odkaz, přes který si nastavíte nové.
        </div>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="E-mail" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <AuthButton :processing="form.processing">Poslat odkaz pro obnovení hesla</AuthButton>
            </div>
        </form>

        <div class="mt-6 text-center">
            <Link :href="route('login')" class="text-sm font-medium text-teal-700 hover:text-teal-900 hover:underline">
                ← Zpět na přihlášení
            </Link>
        </div>
    </GuestLayout>
</template>
