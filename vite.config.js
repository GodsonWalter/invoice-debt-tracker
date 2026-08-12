import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/public-home.css', 'resources/css/theme.css', 'resources/js/app.js', 'resources/js/theme.js', 'resources/js/table-search.js'],
            refresh: true,
        }),
    ],
});
