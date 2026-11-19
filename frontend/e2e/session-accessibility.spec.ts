import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { makeDraft, openNavigation, signInAs, switchToRole } from './helpers';

test('refresh preserves current demo user, deep link and locally saved request', async ({
  page,
}) => {
  await signInAs(page, 'requester');
  await makeDraft(page, 'Session persistence verification');
  const url = page.url();
  await page.reload();
  await expect(page).toHaveURL(url);
  await expect(
    page.getByRole('heading', { name: 'Session persistence verification' }),
  ).toBeVisible();
  await expect(page.locator('.demo-strip').getByRole('combobox')).toHaveValue('requester');
  await switchToRole(page, 'admin');
  await openNavigation(page, 'Purchase requests');
  await page.reload();
  await expect(page).toHaveURL(/\/requests$/);
  await expect(page.locator('.demo-strip').getByRole('combobox')).toHaveValue('admin');
  await page.getByRole('button', { name: 'Sign out' }).click();
  await expect(page).toHaveURL(/\/login$/);
  await page.reload();
  await expect(page).toHaveURL(/\/login$/);
});

test('reset demo clears synthetic changes but retains the current demo role', async ({ page }) => {
  await signInAs(page, 'requester');
  await makeDraft(page, 'Temporary request to be cleared');
  await page.getByRole('button', { name: 'Reset demo' }).click();
  await expect(page).toHaveURL(/\/$/);
  await expect(page.locator('.demo-strip').getByRole('combobox')).toHaveValue('requester');
  await openNavigation(page, 'Purchase requests');
  await expect(page.getByText('Temporary request to be cleared')).not.toBeVisible();
});

test('keyboard skip link and dialog focus management', async ({ page }) => {
  await signInAs(page, 'admin');
  await page.reload();
  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: 'Skip to main content' })).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('#main-content')).toBeFocused();
  await openNavigation(page, 'Purchase requests');
  await page.getByRole('link', { name: 'View Cloud infrastructure renewal' }).click();
  await page.getByRole('button', { name: 'Add quote' }).click();
  const dialog = page.getByRole('dialog', { name: 'Record supplier quote' });
  await expect(dialog).toBeVisible();
  await expect(dialog.getByRole('button', { name: 'Close dialog' })).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(dialog).not.toBeVisible();
  await expect(page.getByRole('button', { name: 'Add quote' })).toBeFocused();
});

for (const route of ['/login', '/engineering'] as const) {
  test(`axe critical/serious WCAG A/AA audit: ${route}`, async ({ page }) => {
    await page.goto(route);
    const result = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();
    const severe = result.violations.filter(
      (v) => v.impact === 'critical' || v.impact === 'serious',
    );
    expect(
      severe.map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.map((n) => n.target) })),
    ).toEqual([]);
  });
}

test('axe critical/serious WCAG audit: authenticated dashboard', async ({ page }) => {
  await signInAs(page);
  const result = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();
  const severe = result.violations.filter((v) => v.impact === 'critical' || v.impact === 'serious');
  expect(
    severe.map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.map((n) => n.target) })),
  ).toEqual([]);
});
