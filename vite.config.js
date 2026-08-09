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
                    // the main app chunk without pulling in every language.
                    'monaco-editor': ['monaco-editor/esm/vs/editor/editor.api.js'],
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
