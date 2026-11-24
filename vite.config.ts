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
        laravel({ input: ['resources/js/main.tsx', 'resources/js/pages/Landing/Landing.tsx'], refresh: true }),
        react(),
    ],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    // Usado só pelo npm run dev (make dev); o build de produção ignora este bloco.
    server: {
        port: Number(process.env.VITE_PORT ?? 5173),
        strictPort: true,
        // O navegador acessa o container pela porta publicada no host.
        hmr: { host: 'localhost' },
        watch: {
            usePolling: process.env.VITE_USE_POLLING === 'true',
            ignored: ['**/vendor/**', '**/storage/**', '**/public/build/**', '**/.git/**'],
        },
    },
});
