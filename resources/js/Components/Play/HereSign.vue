<!-- "You're here, look around": replaces the direction when the player is
     so close that GPS noise makes any arrow random (directionUnreliable). -->
<template>
    <div class="relative flex h-full w-full items-center justify-center" aria-hidden="true">
        <span class="absolute h-full w-full rounded-full bg-white/30 motion-safe:animate-ping"></span>
        <svg viewBox="0 0 48 48" class="relative h-2/3 w-2/3 drop-shadow-[0_4px_8px_rgba(15,23,42,0.45)]">
            <circle cx="24" cy="24" r="21" class="fill-white" />
            <circle cx="24" cy="24" r="15" class="fill-orange-500" />
            <circle cx="24" cy="24" r="9" class="fill-white" />
            <circle cx="24" cy="24" r="4" class="fill-orange-500" />
        </svg>
    </div>
</template>
