import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let instance = null;

// Created lazily so only the dozor's tracking page opens a WebSocket —
// players' phones never need one.
//
// Returns null when Reverb isn't configured (e.g. a fresh checkout with
// empty REVERB_APP_* / VITE_REVERB_APP_KEY) instead of throwing — pusher-js
// requires a non-empty key to construct a client. Callers must handle a
// null return by skipping subscribe/leave.
export function getEcho() {
    if (instance) return instance;

    if (!import.meta.env.VITE_REVERB_APP_KEY) {
        return null;
    }

    window.Pusher = Pusher;
    instance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        // Custom authorizer using axios to include XSRF token from cookie.
        // app.blade.php has no csrf meta tag, so we route private-channel auth
        // through window.axios (configured in bootstrap.js) which automatically
        // sends the X-XSRF-TOKEN header, same mechanism as all other API calls.
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                window.axios
                    .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
                    .then((response) => callback(null, response.data))
                    .catch((error) => callback(error));
            },
        }),
    });

    return instance;
}
