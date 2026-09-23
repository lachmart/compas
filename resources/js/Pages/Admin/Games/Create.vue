<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Card from '@/Components/UI/Card.vue';
import FormField from '@/Components/UI/FormField.vue';
import UIButton from '@/Components/UI/Button.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({ name: '' });

const submit = () => form.post(route('admin.games.store'));
</script>

<template>
    <Head title="Nová hra" />

    <AuthenticatedLayout>
        <template #header>Nová hra</template>

        <Card>
            <form @submit.prevent="submit" class="max-w-xl space-y-6">
                <FormField label="Název hry" hint="Uvidíš ho jen ty, dětem se nezobrazuje." for="name" :error="form.errors.name">
                    <TextInput id="name" v-model="form.name" type="text" class="block w-full" autofocus />
                </FormField>

                <div class="flex items-center gap-3">
                    <UIButton type="submit" :processing="form.processing">Vytvořit a pokračovat na mapu</UIButton>
                    <UIButton variant="secondary" :href="route('admin.games.index')">Zpět</UIButton>
                </div>
            </form>
        </Card>
    </AuthenticatedLayout>
</template>
