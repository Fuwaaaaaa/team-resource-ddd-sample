import { test, expect } from '@playwright/test';
import { loginAs } from './helpers';

test.describe('RBAC enforcement', () => {
  test('viewer accessing /admin/users is shown the Forbidden surface', async ({ page }) => {
    await loginAs(page, 'viewer');
    await page.goto('/admin/users');

    // 画面遷移は許す (middleware は XSRF Cookie のみ見る) が、
    // ページ内で <Forbidden /> が表示され、admin 操作 UI が見えない、を担保する。
    await expect(page.getByText(/forbidden|権限|許可/i).first()).toBeVisible();
    // admin だけが触れる "Create user" 系のボタンが存在しないことを確認
    await expect(page.getByRole('button', { name: /Create user|ユーザー作成/i })).toHaveCount(0);
  });

  test('admin reaches /admin/users and can see the user table', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/users');

    // シードされた 3 ユーザーのうち少なくとも 1 件が見える
    await expect(page.getByText(/admin@example\.com/)).toBeVisible();
  });
});
