import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { cpSync, mkdirSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';

function civilinaBrowserModules() {
    return {
        name: 'civilina-browser-modules',
        closeBundle() {
            const source = resolve('resources/js/civilina-ai');
            const target = resolve('public/build/civilina-ai');
            rmSync(target, { recursive: true, force: true });
            mkdirSync(target, { recursive: true });
            cpSync(source, target, { recursive: true });
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        civilinaBrowserModules(),
    ],
});
