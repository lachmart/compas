<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import PermissionsSheet from '@/Components/Play/PermissionsSheet.vue';
import { AVATARS } from '@/lib/avatars';
import { unlockAudio } from '@/lib/feedback';
import { setCameraPreference } from '@/lib/cameraPreference';
import { requestOrientationPermission } from '@/lib/orientationPermission';

const props = defineProps({
    game: Object,
    // Animals other children in this game already have; shown faded and
    // not pickable. Refreshed while the screen is open.
    taken_avatars: { type: Array, default: () => [] },
});

const isTaken = (key) => props.taken_avatars.includes(key);

// Another child may grab an animal while this screen is open.
const TAKEN_REFRESH_MS = 5000;
let refreshTimer = null;

onMounted(() => {
    refreshTimer = setInterval(() => {
        if (!form.processing) router.reload({ only: ['taken_avatars'] });
    }, TAKEN_REFRESH_MS);
});

onBeforeUnmount(() => clearInterval(refreshTimer));

// Optional name; younger players can have it typed in by an adult.
const NAME_PLACEHOLDER = 'Jm\u00e9no (nepovinn\u00e9)';
// Screen-reader label of the play button ("Play").
const PLAY_LABEL = 'Hr\u00e1t';

const form = useForm({ avatar: null, nickname: '' });

// 'nudge' = gentle pulse when ▶ is tapped before picking an animal,
// 'shake' = the server rejected the avatar. Both are wordless hints.
const hint = ref(null);

// Drop a selection someone else took meanwhile (refresh or a rejected join).
watch(
    () => props.taken_avatars,
    () => {
        if (form.avatar && isTaken(form.avatar)) form.avatar = null;
    },
);

const showHint = (kind) => {
    hint.value = null;
    // Next frame, so the same animation can replay on repeated taps.
    requestAnimationFrame(() => (hint.value = kind));
};

const pick = (key) => {
    if (isTaken(key)) {
        showHint('shake');

        return;
    }

    form.avatar = key;
    form.clearErrors('avatar');
    hint.value = null;
};

// ▶ first explains the permissions; the sheet's buttons then ask for them.
const askingPermissions = ref(false);

const play = () => {
    // Unlock sound for the whole game inside this tap (iOS gesture rule).
    unlockAudio();

    if (form.processing) return;

    if (!form.avatar) {
        showHint('nudge');

        return;
    }

    askingPermissions.value = true;
};

// The camera is only asked for now, never by surprise later: a refusal (or
// "without camera") is remembered and the AR view keeps its plain background.
const askForCamera = () => {
    if (!navigator.mediaDevices?.getUserMedia) {
        setCameraPreference('declined');

        return;
    }

    navigator.mediaDevices
        .getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
        .then((stream) => {
            stream.getTracks().forEach((track) => track.stop());
            setCameraPreference('granted');
        })
        .catch(() => setCameraPreference('declined'));
};

const startGame = (withCamera) => {
    unlockAudio();
    askingPermissions.value = false;

    // All prompts must start inside this tap: iOS only asks for the compass
    // permission during a user gesture. Don't await — the game screen picks
    // the results up from the shared permission state.
    requestOrientationPermission();
    navigator.geolocation?.getCurrentPosition(
        () => {},
        () => {},
    );

    if (withCamera) {
        askForCamera();
    } else {
        setCameraPreference('declined');
    }

    form.post(route('play.join', props.game.slug), {
        onError: (errors) => {
            if (errors.avatar) showHint('shake');
        },
    });
};
</script>

<template>
    <Head :title="game.name" />

    <div class="relative flex min-h-dvh flex-col items-center overflow-x-hidden bg-gradient-to-br from-emerald-100 via-teal-50 to-sky-100 px-4 pb-8 pt-6">
        <!-- Topographic contour lines, like the dozor's login. Decorative. -->
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

        <ApplicationLogo class="relative h-16 w-16 shrink-0 drop-shadow-md sway" />

        <form
            class="relative flex w-full max-w-md flex-1 flex-col items-center justify-center gap-8 py-6"
            @submit.prevent="play"
        >
            <!-- 4×2 animal picker -->
            <div
                class="grid w-full grid-cols-4 place-items-center gap-2 sm:gap-3"
                :class="{ 'hint-shake': hint === 'shake' }"
                role="radiogroup"
                @animationend.self="hint = null"
            >
                <button
                    v-for="(animal, index) in AVATARS"
                    :key="animal.key"
                    type="button"
                    role="radio"
                    :aria-checked="form.avatar === animal.key"
                    :aria-disabled="isTaken(animal.key)"
                    :aria-label="animal.label"
                    class="flex aspect-square w-full max-w-[88px] select-none items-center justify-center rounded-full bg-white shadow-lg shadow-teal-900/10 ring-1 ring-teal-900/5 transition duration-200 ease-out focus:outline-none focus-visible:ring-4 focus-visible:ring-teal-500 active:scale-95"
                    :class="[
                        isTaken(animal.key)
                            ? 'cursor-not-allowed opacity-30 shadow-none grayscale active:scale-100'
                            : form.avatar === animal.key
                            ? 'z-10 scale-110 !ring-4 !ring-orange-400 shadow-orange-500/30 active:scale-105'
                            : form.avatar
                              ? 'opacity-80'
                              : '',
                        { 'hint-nudge': hint === 'nudge' },
                    ]"
                    :style="hint === 'nudge' ? { animationDelay: `${index * 60}ms` } : null"
                    @click="pick(animal.key)"
                    @animationend="index === AVATARS.length - 1 && hint === 'nudge' && (hint = null)"
                >
                    <span
                        :key="form.avatar === animal.key ? 'on' : 'off'"
                        class="text-[clamp(2.25rem,11vw,3.5rem)] leading-none"
                        :class="{ 'pop-in': form.avatar === animal.key }"
                        aria-hidden="true"
                    >{{ animal.emoji }}</span>
                </button>
            </div>

            <!-- Filled in by an adult; small so it doesn't compete with the animals.
                 Text stays 16px: iOS zooms into smaller inputs on focus and the
                 zoom sticks into the game, pushing the compass off-centre. -->
            <input
                v-model="form.nickname"
                type="text"
                maxlength="50"
                autocomplete="off"
                enterkeyhint="go"
                :placeholder="NAME_PLACEHOLDER"
                :aria-label="NAME_PLACEHOLDER"
                class="w-full max-w-60 rounded-full border-0 bg-white/80 px-4 py-2 text-center text-base text-teal-900 shadow-inner ring-1 ring-teal-900/10 placeholder:text-teal-800/50 focus:bg-white focus:ring-2 focus:ring-teal-500"
                :class="{ '!ring-2 !ring-red-400': form.errors.nickname }"
            />

            <!-- aria-disabled, not disabled: a tap before picking must still reach play() to fire the nudge hint. -->
            <button
                type="submit"
                :aria-disabled="!form.avatar || form.processing"
                :aria-label="PLAY_LABEL"
                class="flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-b from-emerald-400 to-emerald-600 text-white shadow-xl shadow-emerald-700/30 ring-4 ring-white/70 transition duration-200 focus:outline-none focus-visible:ring-teal-500 active:scale-95"
                :class="
                    form.avatar
                        ? 'breathe'
                        : 'opacity-40 saturate-50'
                "
            >
                <svg v-if="!form.processing" viewBox="0 0 24 24" class="ml-1.5 h-12 w-12" fill="currentColor" aria-hidden="true">
                    <path d="M7 4.5v15a1 1 0 0 0 1.52.85l12-7.5a1 1 0 0 0 0-1.7l-12-7.5A1 1 0 0 0 7 4.5Z" />
                </svg>
                <svg v-else viewBox="0 0 24 24" class="h-10 w-10 motion-safe:animate-spin" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true">
                    <path d="M12 3a9 9 0 1 0 9 9" />
                </svg>
            </button>
        </form>

        <PermissionsSheet v-if="askingPermissions" @start="startGame" @close="askingPermissions = false" />
    </div>
</template>

<style scoped>
@keyframes kompas-sway {
    0%, 100% { transform: rotate(-6deg); }
    50% { transform: rotate(6deg); }
}

@keyframes kompas-breathe {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.06); }
}

@keyframes pop-in {
    0% { transform: scale(0.6) rotate(-12deg); }
    55% { transform: scale(1.25) rotate(8deg); }
    100% { transform: scale(1) rotate(0); }
}

@keyframes nudge {
    0%, 100% { transform: scale(1); }
    40% { transform: scale(1.14) translateY(-6px); }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    15%, 55% { transform: translateX(-10px); }
    35%, 75% { transform: translateX(10px); }
}

@media (prefers-reduced-motion: no-preference) {
    .sway {
        animation: kompas-sway 6s ease-in-out infinite;
    }

    .breathe {
        animation: kompas-breathe 2.4s ease-in-out infinite;
    }

    .pop-in {
        animation: pop-in 420ms cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .hint-nudge {
        animation: nudge 520ms ease-in-out;
    }

    .hint-shake {
        animation: shake 450ms ease-in-out;
    }
}

/* Without motion, still flag the problem — just without movement. */
@media (prefers-reduced-motion: reduce) {
    .hint-nudge,
    .hint-shake > * {
        box-shadow: 0 0 0 4px rgb(251 146 60);
    }
}
</style>
