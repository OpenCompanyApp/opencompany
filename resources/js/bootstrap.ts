import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        axios: typeof axios;
        Pusher: typeof Pusher;
        Echo: Echo<any>;
    }
}

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Configure Pusher for Laravel Reverb
// Auto-detect host/scheme from the current page so WebSocket connects through
// the same origin (works with Valet, ngrok, or any reverse proxy).
window.Pusher = Pusher;
Pusher.logToConsole = true;

const wsHost = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
const wsScheme = import.meta.env.VITE_REVERB_SCHEME || (window.location.protocol === 'https:' ? 'https' : 'http');
const isTLS = wsScheme === 'https';
const wsPort = Number(import.meta.env.VITE_REVERB_PORT) || (isTLS ? 443 : 80);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost,
    wsPort,
    wssPort: wsPort,
    forceTLS: isTLS,
    enabledTransports: ['ws', 'wss'],
});
