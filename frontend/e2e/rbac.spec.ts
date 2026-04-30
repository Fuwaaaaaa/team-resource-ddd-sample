import { test, expect } from '@playwright/test';
import { loginAs } from './helpers';

test.describe('RBAC enforcement', () => {
  test('viewer accessing /admin/users is shown the Forbidden surface', async ({ page }) => {
    await loginAs(page, 'viewer');
    await page.goto('/admin/users');

    // 画面遷移は許す (middleware は XSRF Cookie のみ見る) が、
    // ページ内で <Forbidden /> が表示され、admin 操作 UI が見えない、を担保する。
    // ja default: "アクセス権限がありません" / en: "Access denied"
    await expect(page.getByText(/access denied|権限|denied/i).first()).toBeVisible();
    // admin だけが触れる "+ 新規ユーザー" / "+ Create user" ボタンが存在しないことを確認
    await expect(page.getByRole('button', { name: /新規ユーザー|create user/i })).toHaveCount(0);
  });

  test('admin reaches /admin/users and can see the user table', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin/users');

    // admin@example.com は AppHeader (ログイン中ユーザー) と user table 行の
    // 両方に表示されるため、 table 内に絞り込んで判定する (strict mode 違反回避)。
    await expect(page.locator('table').getByText(/admin@example\.com/).first()).toBeVisible();
  });
});
