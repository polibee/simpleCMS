import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import '../css/app.css';

const pages = import.meta.glob([
    './Pages/**/*.vue',
    '../../modules/*/resources/js/Pages/**/*.vue',
]);

createInertiaApp({
    title: (title) => `${title} · ${window.__APP_NAME__ ?? 'CMSForum'}`,
    resolve: async (name) => {
        // 应用页：./Pages/{name}.vue
        // 模块页：{Module}/{Path} → modules/{module}/resources/js/Pages/{Module}/{Path}.vue
        const importPage = pages[`./Pages/${name}.vue`]
            ?? pages[`../../modules/${name.split('/')[0].toLowerCase()}/resources/js/Pages/${name}.vue`];

        if (!importPage) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return importPage();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4f46e5',
    },
});
