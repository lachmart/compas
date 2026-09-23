<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    fullBleed: { type: Boolean, default: false },
});

const showingNavigationDropdown = ref(false);

const gamesLinkClasses = (active) =>
    active
        ? 'inline-flex items-center border-b-2 border-teal-500 px-1 pt-1 text-sm font-medium text-teal-800'
        : 'inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-slate-500 hover:border-slate-300 hover:text-slate-700';
</script>

<template>
    <div :class="fullBleed ? 'flex h-dvh flex-col bg-slate-50' : 'min-h-screen bg-slate-50'">
        <div class="bg-white border-b border-slate-200">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 justify-between">
                    <div class="flex">
                        <div class="flex shrink-0 items-center">
                            <Link :href="route('admin.games.index')" class="flex items-center gap-2">
                                <ApplicationLogo class="h-8 w-8" />
                                <span class="text-lg font-bold tracking-tight text-teal-900">Kompas</span>
                            </Link>
                        </div>

                        <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                            <Link
                                :href="route('admin.games.index')"
                                :class="gamesLinkClasses(route().current('admin.games.*'))"
                            >
                                Moje hry
                            </Link>
                        </div>
                    </div>

                    <div class="hidden sm:ms-6 sm:flex sm:items-center">
                        <div class="relative ms-3">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <span class="inline-flex rounded-md">
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-slate-500 transition duration-150 ease-in-out hover:text-slate-700 focus:outline-none"
                                        >
                                            {{ $page.props.auth.user.name }}

                                            <svg
                                                class="-me-0.5 ms-2 h-4 w-4"
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </button>
                                    </span>
                                </template>

                                <template #content>
                                    <DropdownLink :href="route('profile.edit')"> Profil </DropdownLink>
                                    <DropdownLink :href="route('logout')" method="post" as="button"> Odhlásit se </DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <div class="-me-2 flex items-center sm:hidden">
                        <button
                            type="button"
                            aria-label="Menu"
                            :aria-expanded="showingNavigationDropdown"
                            @click="showingNavigationDropdown = !showingNavigationDropdown"
                            class="inline-flex items-center justify-center rounded-md p-2 text-slate-400 transition duration-150 ease-in-out hover:bg-slate-100 hover:text-slate-500 focus:bg-slate-100 focus:text-slate-500 focus:outline-none"
                        >
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path
                                    :class="{
                                        hidden: showingNavigationDropdown,
                                        'inline-flex': !showingNavigationDropdown,
                                    }"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path
                                    :class="{
                                        hidden: !showingNavigationDropdown,
                                        'inline-flex': showingNavigationDropdown,
                                    }"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div
                :class="{
                    block: showingNavigationDropdown,
                    hidden: !showingNavigationDropdown,
                }"
                class="sm:hidden"
            >
                <div class="space-y-1 pb-3 pt-2">
                    <Link
                        :href="route('admin.games.index')"
                        class="block border-l-4 py-2 ps-3 pe-4 text-start text-base font-medium transition duration-150 ease-in-out"
                        :class="
                            route().current('admin.games.*')
                                ? 'border-teal-500 bg-teal-50 text-teal-800'
                                : 'border-transparent text-slate-600 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-800'
                        "
                    >
                        Moje hry
                    </Link>
                </div>

                <div class="border-t border-slate-200 pb-1 pt-4">
                    <div class="px-4">
                        <div class="text-base font-medium text-slate-800">{{ $page.props.auth.user.name }}</div>
                        <div class="text-sm font-medium text-slate-500">{{ $page.props.auth.user.email }}</div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <DropdownLink :href="route('profile.edit')"> Profil </DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button"> Odhlásit se </DropdownLink>
                    </div>
                </div>
            </div>

            <div
                v-if="$slots.breadcrumb || $slots.tabs"
                class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8"
            >
                <div v-if="$slots.breadcrumb" class="flex min-w-0 flex-1 items-center gap-2 text-sm text-slate-500">
                    <slot name="breadcrumb" />
                </div>
                <div v-if="$slots.tabs" class="flex shrink-0 items-center">
                    <slot name="tabs" />
                </div>
            </div>
        </div>

        <main v-if="fullBleed" class="flex min-h-0 flex-1">
            <slot />
        </main>

        <template v-else>
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <div v-if="$slots.header || $slots.actions" class="mb-6 flex items-center justify-between">
                    <h1 v-if="$slots.header" class="text-2xl font-bold text-slate-900">
                        <slot name="header" />
                    </h1>
                    <div v-if="$slots.actions" class="flex items-center gap-2">
                        <slot name="actions" />
                    </div>
                </div>

                <slot />
            </div>
        </template>
    </div>
</template>
