import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';
// Dev proxies the admin API to the live backend so the SPA runs on localhost without CORS.
// In production the SPA is served from admin.salamheyetimiz.com → same-origin, no proxy needed.
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: { '@': path.resolve(__dirname, './src') },
    },
    server: {
        port: 5173,
        proxy: {
            '/admin/v1': { target: 'https://admin.salamheyetimiz.com', changeOrigin: true, secure: true },
            '/.well-known': { target: 'https://admin.salamheyetimiz.com', changeOrigin: true, secure: true },
        },
    },
});
