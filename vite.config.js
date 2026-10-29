import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/charts-dashboard.js',
                'resources/js/charts-utm.js',
                'resources/js/charts-demand.js',
                'resources/js/ai-research-chat.js',
            ],
            refresh: true,
        }),
    ],
});
