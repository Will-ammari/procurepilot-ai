import {defineConfig} from 'vitest/config';
import react from '@vitejs/plugin-react';

// Unit tests only. Browser specs are owned exclusively by Playwright.
export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'node',
    include: ['src/**/*.test.{ts,tsx}', 'src/**/*.spec.{ts,tsx}'],
    exclude: ['e2e/**', 'dist/**', 'node_modules/**', 'test-results/**', 'playwright-report/**'],
  },
});
