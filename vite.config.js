import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    build: {
        chunkSizeWarningLimit: 900,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    const normalizedId = id.replace(/\\/g, '/');

                    if (!normalizedId.includes('/node_modules/')) {
                        return undefined;
                    }

                    if (normalizedId.includes('/node_modules/mathlive/')) {
                        return 'vendor-mathlive';
                    }

                    if (
                        normalizedId.includes('/node_modules/three/') ||
                        normalizedId.includes('/node_modules/@tresjs/')
                    ) {
                        return 'vendor-3d';
                    }

                    if (
                        normalizedId.includes('/node_modules/echarts/') ||
                        normalizedId.includes('/node_modules/vue-echarts/')
                    ) {
                        return 'vendor-charts';
                    }

                    if (
                        normalizedId.includes('/node_modules/@tiptap/') ||
                        normalizedId.includes('/node_modules/prosemirror-')
                    ) {
                        return 'vendor-editor';
                    }

                    if (normalizedId.includes('/node_modules/lucide-vue-next/')) {
                        return 'vendor-icons';
                    }

                    if (
                        normalizedId.includes('/node_modules/vue/') ||
                        normalizedId.includes('/node_modules/@vue/') ||
                        normalizedId.includes('/node_modules/@inertiajs/')
                    ) {
                        return 'vendor-vue';
                    }

                    return undefined;
                },
            },
        },
    },
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
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
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
