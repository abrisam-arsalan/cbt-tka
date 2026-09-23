import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    resolve: {
        alias: {
            // '@' menunjuk ke resources/js/Pages agar import layout antar
            // halaman (satu level maupun dua level subfolder) seragam.
            '@': fileURLToPath(new URL('./resources/js/Pages', import.meta.url)),
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
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
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        // Server sekolah memakai HDD; satu bundle menengah lebih murah
        // daripada banyak chunk kecil yang harus di-stat ulang.
        chunkSizeWarningLimit: 1600,
        assetsInlineLimit: 4096,
    },
});
