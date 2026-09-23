<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import AuthDivider from '@/Components/Auth/AuthDivider.vue';
import AuthLinkButton from '@/Components/Auth/AuthLinkButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Přihlášení" />

        <h1 class="mb-6 text-2xl font-bold text-gray-900">Přihlášení dozoru</h1>

        <div v-if="status" class="mb-4 rounded-lg bg-green-50 p-3 text-sm font-medium text-green-700">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
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

            <div>
                <InputLabel for="password" value="Heslo" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between gap-4">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-sm text-gray-600">Zapamatovat si mě</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm font-medium text-teal-700 hover:text-teal-900 hover:underline"
                >
                    Zapomněli jste heslo?
                </Link>
            </div>

            <AuthButton :processing="form.processing">Přihlásit se</AuthButton>
        </form>

        <AuthDivider />

        <AuthLinkButton :href="route('register')">Vytvořit účet</AuthLinkButton>
    </GuestLayout>
</template>
