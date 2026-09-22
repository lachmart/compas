<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ name: '' });

const submit = () => form.post(route('admin.games.store'));
</script>

<template>
    <Head title="Nová hra" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Nová hra</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-xl sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-4 bg-white p-6 shadow-sm sm:rounded-lg">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Název hry</label>
                        <input v-model="form.name" type="text" class="mt-1 block w-full rounded border-gray-300" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <button type="submit" :disabled="form.processing" class="rounded bg-indigo-600 px-4 py-2 text-white">
                        Vytvořit
                    </button>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
