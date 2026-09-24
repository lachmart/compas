<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-emerald-100 via-teal-50 to-sky-100 px-4 py-10">
        <!-- Topographic contour lines, like a hiking map. Purely decorative. -->
        <svg
            class="pointer-events-none absolute inset-0 h-full w-full text-teal-700/10"
            preserveAspectRatio="xMidYMid slice"
            viewBox="0 0 800 600"
            fill="none"
            stroke="currentColor"
            stroke-width="1.5"
            aria-hidden="true"
        >
            <path d="M-50 120 C 120 60, 260 180, 420 110 S 700 40, 860 140" />
            <path d="M-50 160 C 110 100, 270 220, 430 150 S 710 80, 860 180" />
            <path d="M-50 200 C 100 140, 280 260, 440 190 S 720 120, 860 220" />
            <path d="M-50 430 C 140 380, 260 500, 420 440 S 690 360, 860 470" />
            <path d="M-50 470 C 130 420, 270 540, 430 480 S 700 400, 860 510" />
            <path d="M-50 510 C 120 460, 280 580, 440 520 S 710 440, 860 550" />
            <ellipse cx="660" cy="300" rx="70" ry="40" />
            <ellipse cx="660" cy="300" rx="110" ry="68" />
            <ellipse cx="130" cy="320" rx="50" ry="30" />
            <ellipse cx="130" cy="320" rx="85" ry="52" />
        </svg>

        <div class="relative w-full max-w-md">
            <Link href="/" class="flex flex-col items-center text-center">
                <ApplicationLogo class="h-16 w-16 drop-shadow-sm" />
                <span class="mt-3 text-3xl font-bold tracking-tight text-teal-900">Kompas</span>
            </Link>
            <p class="mt-1 text-center text-sm text-teal-800/80">
                Venkovní hra s kompasem a úkoly
            </p>

            <div class="mt-6 rounded-2xl bg-white px-6 py-8 shadow-xl shadow-teal-900/10 ring-1 ring-teal-900/5 sm:px-8">
                <slot />
            </div>
        </div>
    </div>
</template>
