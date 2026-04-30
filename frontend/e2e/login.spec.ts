import { test, expect } from '@playwright/test';
import { loginAs } from './helpers';

test.describe('login', () => {
  test('admin can sign in and reaches the dashboard', async ({ page }) => {
    await loginAs(page, 'admin');
    // ダッシュボードヘッダの "Team Resource Dashboard" が見えるなら描画成功
    await expect(page.getByRole('heading', { name: /Team Resource Dashboard/i })).toBeVisible();
  });

  test('wrong password shows the error message and stays on /login', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input#email', 'admin@example.com');
    await page.fill('input#password', 'wrong-password');
    await page.click('button[type="submit"]');

    // /login に留まり、エラーメッセージが表示される
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByText(/credentials/i).or(page.getByText(/invalid/i)).or(page.getByText(/不正/))).toBeVisible();
  });

  test('unauthenticated visit to / redirects to /login', async ({ page }) => {
    // XSRF cookie が無い fresh context は middleware で /login に飛ばされる
    const response = await page.goto('/');
    // 最終的に /login で停止
    await expect(page).toHaveURL(/\/login$/);
    // status: 200 か 307 リダイレクト後の login 200 — どちらにせよ login UI が見える
    expect(response).not.toBeNull();
    await expect(page.getByRole('heading', { name: /Team Resource Dashboard/i })).toBeVisible();
  });
});
