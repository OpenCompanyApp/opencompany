import '../css/app.css';
import '@fontsource-variable/lexend';
import 'highlight.js/styles/github-dark.css';
import './bootstrap';

// Initialize color mode (handles dark/light mode)
import './composables/useColorMode';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, DefineComponent, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import AppLayout from './Layouts/AppLayout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Olympus';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: async (name) => {
        const page = await resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        );

        // Use AppLayout for all pages except Welcome and Auth pages
        const excludedPages = ['Welcome', 'Auth/Login', 'Auth/Register', 'Auth/ForgotPassword', 'Auth/ResetPassword', 'Auth/VerifyEmail', 'Auth/ConfirmPassword'];
        if (!excludedPages.includes(name)) {
            page.default.layout = page.default.layout || AppLayout;
        }

        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#6366f1',
    },
});
