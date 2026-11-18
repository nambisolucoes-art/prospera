import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';

export default defineConfig({
    publicDir: 'public',
    build: { copyPublicDir: false },
    experimental: {
        renderBuiltUrl(filename, { type }) {
            // Laravel serves the original theme assets from the public root.
            if (type === 'public') return `/${filename}`;
        },
    },
    plugins: [
        laravel({ input: 'resources/js/main.tsx', refresh: true }),
        react(),
    ],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
});
