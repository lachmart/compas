<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ConfirmDialog from '@/Components/Admin/ConfirmDialog.vue';
import Button from '@/Components/UI/Button.vue';
import FormField from '@/Components/UI/FormField.vue';
import { deleteGameWarning } from '@/lib/editorPanel';

const props = defineProps({
    game: { type: Object, required: true },
    playersCount: { type: Number, default: 0 },
});

const open = ref(false);
const confirmingDelete = ref(false);
const deleting = ref(false);

const form = useForm({
    name: props.game.name,
    slug: props.game.slug,
});

const submit = () => form.put(route('admin.games.update', props.game.id), { preserveScroll: true });

const deleteMessage = computed(() => `Smazat hru \u201e${props.game.name}\u201c?`);

const confirmDelete = () => {
    deleting.value = true;
    router.delete(route('admin.games.destroy', props.game.id), {
        onSuccess: () => {
            confirmingDelete.value = false;
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
};

const inputClasses =
    'block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500';
</script>

<template>
    <section class="rounded-2xl ring-1 ring-slate-900/10">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 rounded-2xl px-4 py-3 text-left transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
            :aria-expanded="open"
            aria-controls="game-settings"
            @click="open = !open"
        >
            <span class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <svg class="h-5 w-5 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.84 1.804A1 1 0 0 1 8.82 1h2.36a1 1 0 0 1 .98.804l.331 1.652a6.993 6.993 0 0 1 1.929 1.115l1.598-.54a1 1 0 0 1 1.186.447l1.18 2.044a1 1 0 0 1-.205 1.251l-1.267 1.113a7.047 7.047 0 0 1 0 2.228l1.267 1.113a1 1 0 0 1 .206 1.25l-1.18 2.045a1 1 0 0 1-1.187.447l-1.598-.54a6.993 6.993 0 0 1-1.929 1.115l-.33 1.652a1 1 0 0 1-.98.804H8.82a1 1 0 0 1-.98-.804l-.331-1.652a6.993 6.993 0 0 1-1.929-1.115l-1.598.54a1 1 0 0 1-1.186-.447l-1.18-2.044a1 1 0 0 1 .205-1.251l1.267-1.114a7.05 7.05 0 0 1 0-2.227L1.821 7.773a1 1 0 0 1-.206-1.25l1.18-2.045a1 1 0 0 1 1.187-.447l1.598.54A6.992 6.992 0 0 1 7.51 3.456l.33-1.652ZM10 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" />
                </svg>
                Nastavení hry
            </span>
            <svg
                class="h-5 w-5 text-slate-400 transition-transform"
                :class="open ? 'rotate-180' : ''"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </button>

        <div v-show="open" id="game-settings" class="space-y-6 border-t border-slate-100 px-4 pb-4 pt-4">
            <form class="space-y-4" @submit.prevent="submit">
                <FormField label="Název hry" for="game-name" :error="form.errors.name">
                    <input id="game-name" v-model="form.name" type="text" maxlength="255" :class="inputClasses" />
                </FormField>

                <FormField label="Adresa hry" for="game-slug" :error="form.errors.slug">
                    <input
                        id="game-slug"
                        v-model="form.slug"
                        type="text"
                        maxlength="255"
                        autocapitalize="off"
                        spellcheck="false"
                        :class="inputClasses"
                    />
                    <p class="mt-1 truncate text-xs text-slate-500">
                        …/hra/<span class="font-medium text-slate-700">{{ form.slug || '…' }}</span>
                    </p>
                </FormField>

                <div class="flex items-center gap-3">
                    <Button type="submit" size="sm" :processing="form.processing">Uložit</Button>
                    <Transition
                        enter-active-class="transition ease-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in"
                        leave-to-class="opacity-0"
                    >
                        <p v-if="form.recentlySuccessful" class="text-sm text-teal-700">Uloženo.</p>
                    </Transition>
                </div>
            </form>

            <div class="rounded-xl bg-red-50/60 p-4 ring-1 ring-red-200">
                <h3 class="text-sm font-semibold text-red-800">Nebezpečná zóna</h3>
                <p class="mt-0.5 text-xs text-red-700/80">Smazání hry nejde vrátit zpět.</p>
                <Button variant="danger" size="sm" class="mt-3" @click="confirmingDelete = true">Smazat hru</Button>
            </div>
        </div>

        <ConfirmDialog
            :show="confirmingDelete"
            :message="deleteMessage"
            :warning="deleteGameWarning(playersCount)"
            confirm-label="Smazat hru"
            :processing="deleting"
            @confirm="confirmDelete"
            @cancel="confirmingDelete = false"
        />
    </section>
</template>
