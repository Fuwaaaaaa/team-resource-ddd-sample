import { defineConfig, devices } from '@playwright/test';

/**
 * E2E test config for the Team Resource Dashboard.
 *
 * The whole compose stack (postgres + Laravel + Next + nginx) must be running
 * on http://localhost:8080. CI boots it via `docker compose up -d --build`
 * before running these specs; locally, run `docker compose up` in another shell.
 *
 * Seeded credentials (UserSeeder):
 *   admin@example.com / password
 *   manager@example.com / password
 *   viewer@example.com / password
 */
export default defineConfig({
  testDir: './e2e',
  // Per-test timeout — the dashboard heatmap can take a couple of seconds to
  // resolve on a fresh container with no warm cache.
  timeout: 30_000,
  expect: { timeout: 7_500 },
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : [['list'], ['html', { open: 'on-failure' }]],
  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8080',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    // Sanctum 認証は Cookie ベース。Playwright のデフォルトコンテキストは
    // 各テストで隔離されるため、明示的なセッションリセットは不要。
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
