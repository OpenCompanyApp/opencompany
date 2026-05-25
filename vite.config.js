import { defineConfig } from 'vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        wayfinder({
            command: process.env.WAYFINDER_SKIP_GENERATE === 'true'
                ? 'node -e ""'
                : 'php artisan wayfinder:generate',
        }),
        tailwindcss(),
        laravel({
            input: 'resources/js/app.ts',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    // Monaco is large and editor-specific, so keep it out of
                    // the main app chunk for non-editor pages.
                    'monaco-editor': ['monaco-editor'],
                },
            },
        },
    },
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
