import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css',
            ],
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
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
        // Vite runs inside the container and the sources are bind-mounted in,
        // so a file written on the host raises no filesystem event the
        // container ever hears. Without polling vite serves the new file the
        // moment a browser asks for it - which is why a hard refresh worked -
        // but never learns it changed, so it never tells the browser to. Hot
        // reload looked broken while everything was in fact up to date.
        watch: {
            usePolling: true,
            interval: 300,
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern-compiler',
            },
        },
    },
});
