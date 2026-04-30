import { expect, type Page } from '@playwright/test';

/**
 * 共有 helper: シードされたデモ user で sign-in する。
 *
 * 単純なロジック (Login フォームに値を埋めて submit するだけ) なので
 * Page Object Model を導入するほどでもない。Spec ごとに何度も書かれる
 * 5 行を 1 箇所にまとめておくだけ。
 */
export async function loginAs(page: Page, role: 'admin' | 'manager' | 'viewer'): Promise<void> {
  await page.goto('/login');
  await page.fill('input#email', `${role}@example.com`);
  await page.fill('input#password', 'password');
  await page.click('button[type="submit"]');
  // Login 成功時はホーム ('/') に push される
  await expect(page).toHaveURL('/');
}
