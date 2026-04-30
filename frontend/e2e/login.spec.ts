import { test, expect } from '@playwright/test';
import { loginAs } from './helpers';

test.describe('login', () => {
  test('admin can sign in and reaches the dashboard', async ({ page }) => {
    await loginAs(page, 'admin');
    // / のメイン見出しは "Resource Heatmap" (frontend/src/app/page.tsx)
    await expect(page.getByRole('heading', { name: 'Resource Heatmap' })).toBeVisible();
  });

  test('wrong password shows the error message and stays on /login', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input#email', 'admin@example.com');
    await page.fill('input#password', 'wrong-password');
    await page.click('button[type="submit"]');

    // /login に留まり、エラーメッセージが表示される
    // (Laravel の "These credentials do not match..." / 422 / 認証関連のいずれか)
    await expect(page).toHaveURL(/\/login$/);
    await expect(
      page.getByText(/credentials/i).or(page.getByText(/invalid/i)).or(page.getByText(/不正|認証/)),
    ).toBeVisible();
  });

  test('unauthenticated visit to / redirects to /login', async ({ page }) => {
    // XSRF cookie が無い fresh context は middleware で /login に飛ばされる
    const response = await page.goto('/');
    expect(response).not.toBeNull();
    await expect(page).toHaveURL(/\/login$/);
    // /login の見出しは "Team Resource Dashboard" (sign-in form の見出し)
    await expect(page.getByRole('heading', { name: /Team Resource Dashboard/i })).toBeVisible();
  });
});
