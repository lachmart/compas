import { onUnmounted, watch } from 'vue';
import { useSensors } from '@/lib/useSensors';
import { shouldSendPing } from '@/lib/pings';
import { isSessionGone, reloadForGoneSession } from '@/lib/playSession';

const PING_INTERVAL_MS = 5000;

// Runs a background position-ping loop for a player session. Owns its own
// geolocation/heading watch (via useSensors) independent of whatever screen
// is currently shown, so the dozor's tracking page keeps getting updates
// while a player is on the map, doing a task, or looking at a reward —
// not just while the compass is mounted.
//
// `enabled` is a ref/computed<boolean>: pinging starts once it becomes true
// (after permissions are granted) and stops once it becomes false (once the
// game is finished), with the interval torn down on unmount either way.
export function usePositionPings(gameSlug, enabled) {
    const { position, heading, requestPermissions } = useSensors();

    let lastPing = null;
    let timer = null;

    const tick = () => {
        if (!shouldSendPing(lastPing, position.value, Date.now())) return;

        lastPing = { lat: position.value.lat, lng: position.value.lng, at: Date.now() };

        window.axios
            .post(route('play.pings.store', gameSlug), {
                lat: position.value.lat,
                lng: position.value.lng,
                heading: heading.value,
            })
            .catch((error) => {
                // The player was deleted mid-game: stop pinging and send the
                // browser back to the join screen instead of retrying forever.
                if (isSessionGone(error)) {
                    stop();
                    reloadForGoneSession();
                }
            });
    };

    const start = () => {
        if (timer) return;
        requestPermissions();
        timer = setInterval(tick, PING_INTERVAL_MS);
    };

    const stop = () => {
        if (!timer) return;
        clearInterval(timer);
        timer = null;
    };

    watch(enabled, (value) => (value ? start() : stop()), { immediate: true });

    onUnmounted(stop);

    return { position, heading };
}
