import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        axios: typeof axios;
        Pusher: typeof Pusher;
        Echo: Echo<any>;
        __reverb?: { key?: string; host?: string; port?: number; scheme?: string };
    }
}

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Configure Pusher for Laravel Reverb
// Reverb config is injected by Blade from server-side config (runtime values).
// Auto-detect host/scheme from the current page so WebSocket connects through
// the same origin (works with Valet, ngrok, or any reverse proxy).
window.Pusher = Pusher;

const reverbConfig = window.__reverb ?? {};
const wsHost = reverbConfig.host || window.location.hostname;
const wsScheme = reverbConfig.scheme || (window.location.protocol === 'https:' ? 'https' : 'http');
const isTLS = wsScheme === 'https';
const wsPort = reverbConfig.port || (isTLS ? 443 : 80);

if (reverbConfig.key) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbConfig.key,
        wsHost,
        wsPort,
        wssPort: wsPort,
        forceTLS: isTLS,
        enabledTransports: ['ws', 'wss'],
    });
}
