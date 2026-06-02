import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: (id: string) => {
                    if (/node_modules\/(vue|pinia|@inertiajs\/vue3)/.test(id)) {
                        return 'vendor-vue';
                    }
                    if (/node_modules\/(reka-ui|lucide-vue-next|vue-sonner)/.test(id)) {
                        return 'vendor-ui';
                    }
                    if (/node_modules\/(@vue-flow|@dagrejs\/dagre)/.test(id)) {
                        return 'vendor-graph';
                    }
                    if (/node_modules\/@vueuse/.test(id)) {
                        return 'vendor-ai';
                    }
                    if (/node_modules\/@tauri-apps/.test(id)) {
                        return 'vendor-tauri';
                    }
                },
            },
        },
        chunkSizeWarningLimit: 500,
        cssMinify: true,
    },
});
