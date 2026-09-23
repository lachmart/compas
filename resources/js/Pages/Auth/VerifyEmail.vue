<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Ověření e-mailu" />

        <h1 class="mb-4 text-2xl font-bold text-gray-900">Ověření e-mailu</h1>

        <div class="mb-4 text-sm text-gray-600">
            Děkujeme za registraci! Než začnete, ověřte prosím svůj e-mail kliknutím na odkaz, který jsme vám právě poslali. Pokud e-mail nedorazil, rádi vám pošleme další.
        </div>

        <div
            class="mb-4 text-sm font-medium text-green-600"
            v-if="verificationLinkSent"
        >
            Na e-mail zadaný při registraci jsme poslali nový ověřovací odkaz.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-6 space-y-4">
                <AuthButton :processing="form.processing">Poslat ověřovací e-mail znovu</AuthButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="block w-full text-center text-sm font-medium text-teal-700 hover:text-teal-900 hover:underline"
                    >Odhlásit se</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>
