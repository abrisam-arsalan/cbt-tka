import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue, route } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Panglima CBT';

// route() dipakai sebagai global di sebagian besar komponen `<script setup>`.
// Membuatnya tersedia di globalThis memastikan template DAN kode script dapat
// memanggil route() tanpa import eksplisit di tiap file.
globalThis.route = route;

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#ea580c',
        showSpinner: true,
    },
});
