/// <reference types="vitest/config" />
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// https://vite.dev/config/
export default defineConfig({
    plugins: [react()],
    server: {
        // The API lets only this exact origin call it (CORS_ADMIN_ORIGIN in api/.env.dev):
        // if port 5173 is taken, Vite must stop rather than move to another port.
        host: 'localhost',
        port: 5173,
        strictPort: true,
    },
    build: {
        // react-admin and Material UI weigh about 270 kB gzipped: expected for a back-office,
        // loaded once and cached. Splitting it would not make the first screen faster.
        chunkSizeWarningLimit: 1000,
    },
    test: {
        // A browser-like environment: localStorage, Response, navigator.
        environment: 'jsdom',
    },
});
