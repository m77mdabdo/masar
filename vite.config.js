import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // big-story.js is its own entry, not an import of app.js: it is
            // fetched after the load event by the component itself, so not one
            // byte of it sits in front of the LCP image (Task 2.10).
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/big-story.js', 'resources/css/filament/admin/theme.css'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
