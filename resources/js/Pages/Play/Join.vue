<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    game: Object,
});

const form = useForm({ nickname: '' });

const submit = () => form.post(route('play.join', props.game.slug));
</script>

<template>
    <Head :title="game.name" />

    <div class="flex h-screen flex-col items-center justify-center gap-6 bg-sky-50 p-6">
        <form @submit.prevent="submit" class="flex flex-col items-center gap-4">
            <label class="text-lg font-semibold text-gray-700">Zadej přezdívku</label>
            <input v-model="form.nickname" type="text" class="rounded-lg border-2 border-sky-300 px-4 py-3 text-center text-xl" autofocus />
            <p v-if="form.errors.nickname" class="text-sm text-red-600">{{ form.errors.nickname }}</p>
            <button type="submit" :disabled="form.processing" class="rounded-full bg-sky-600 px-10 py-4 text-xl text-white active:scale-95">
                Hrát ▶
            </button>
        </form>
    </div>
</template>
