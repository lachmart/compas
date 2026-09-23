import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let instance = null;

// Created lazily so only the dozor's tracking page opens a WebSocket —
// players' phones never need one.
export function getEcho() {
    if (instance) return instance;

    window.Pusher = Pusher;
    instance = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return instance;
}
