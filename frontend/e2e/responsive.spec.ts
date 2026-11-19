import { test, expect } from '@playwright/test';
import { openNavigation, signInAs } from './helpers';

for (const width of [375, 768, 1440]) {
  test(`dashboard, requests and dialogs remain usable at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 844 });
    await signInAs(page);
    await openNavigation(page, 'Purchase requests');
    await expect(
      page.getByRole('heading', { name: 'Purchase requests', exact: true }),
    ).toBeVisible();
    await page.getByRole('link', { name: 'Create request' }).first().click();
    await expect(page.getByLabel(/Request title/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Create draft request' })).toBeVisible();
    // Tables may intentionally scroll inside .table-wrap; the page should not.
    const overflow = await page.evaluate(() => ({
      documentWidth: document.documentElement.scrollWidth,
      viewportWidth: document.documentElement.clientWidth,
    }));
    expect(overflow.documentWidth).toBeLessThanOrEqual(overflow.viewportWidth + 2);
  });
}
