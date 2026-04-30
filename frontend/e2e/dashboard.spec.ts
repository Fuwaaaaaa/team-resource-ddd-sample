import { test, expect } from '@playwright/test';
import { loginAs } from './helpers';

test.describe('dashboard', () => {
  test('admin home loads the heatmap with member rows', async ({ page }) => {
    await loginAs(page, 'admin');

    // ヒートマップ table が描画され、最低 1 行 (= 少なくとも 1 メンバー) は出る
    const table = page.locator('table');
    await expect(table.first()).toBeVisible();

    // シードされたメンバー名が 1 つ以上表示される
    // MemberSeeder は複数名を作るので "Alice" / "Bob" 等のうち少なくとも 1 つは存在する想定
    const cells = page.locator('table tbody tr');
    await expect(cells.first()).toBeVisible();
  });
});
