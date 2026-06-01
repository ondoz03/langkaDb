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
                manualChunks: {
                    'vendor-vue': ['vue', 'pinia', '@inertiajs/vue3'],
                    'vendor-ui': ['reka-ui', 'lucide-vue-next', 'vue-sonner'],
                    'vendor-graph': ['@vue-flow/core', '@vue-flow/background', '@vue-flow/controls', '@vue-flow/minimap', '@dagrejs/dagre'],
                    'vendor-ai': ['@vueuse/core', '@vueuse/motion'],
                },
            },
        },
        chunkSizeWarningLimit: 500,
        cssMinify: true,
        minify: 'esbuild',
    },
});
