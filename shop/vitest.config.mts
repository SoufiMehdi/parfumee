import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [react()],
    // The "@/" imports of the application, read from tsconfig.json.
    resolve: { tsconfigPaths: true },
    test: {
        environment: 'jsdom',
    },
});
