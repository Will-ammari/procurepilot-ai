import { defineConfig, devices } from '@playwright/test';

// E2E exercises the public SIMULATED demo. A separate Laravel API suite is
// required to verify production permissions and database behavior.
export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: process.env.CI ? 2 : undefined,
  timeout: 30_000,
  expect: { timeout: 7_000 },
  reporter: process.env.CI ? [['line'], ['html', {open: 'never'}]] : 'list',
  use: {
    baseURL: 'http://127.0.0.1:4173',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {name: 'chromium-desktop', use: {...devices['Desktop Chrome']}},
    {name: 'chromium-mobile', use: {...devices['Desktop Chrome'], viewport: {width: 390, height: 844}, isMobile: true, hasTouch: true}},
  ],
  webServer: {
    command: 'npm run dev -- --host 127.0.0.1 --port 4173 --strictPort',
    url: 'http://127.0.0.1:4173/login',
    // No reuse: a developer's running API-mode Vite server must never be
    // mistaken for the isolated public demo used by these tests.
    reuseExistingServer: false,
    timeout: 90_000,
    env: {VITE_DATA_MODE: 'demo'},
  },
});
