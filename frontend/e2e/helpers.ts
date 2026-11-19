import { expect, type Page } from '@playwright/test';

type DemoRole = 'admin' | 'requester' | 'procurement' | 'manager' | 'finance' | 'viewer';

/** Each Playwright test gets a fresh browser context (isolated localStorage). */
export async function signInAs(page: Page, role: DemoRole = 'admin') {
  await page.goto('/login');
  await expect(page.getByText('Interactive portfolio demo')).toBeVisible();
  await page.getByLabel('Explore as a team role').selectOption(role);
  await page.getByRole('button', { name: /Enter workspace/ }).click();
  await expect(page).toHaveURL(/\/$/);
  await expect(page.getByRole('heading', { name: /Good to see you/ })).toBeVisible();
}

export async function switchToRole(page: Page, role: DemoRole) {
  await page.locator('.demo-strip').getByRole('combobox').selectOption(role);
  await expect(page).toHaveURL(/\/$/);
  await expect(page.locator('.demo-strip').getByRole('combobox')).toHaveValue(role);
}

export async function openNavigation(page: Page, label: string) {
  const toggle = page.getByRole('button', { name: 'Open navigation' });
  if (await toggle.isVisible()) await toggle.click();
  await page
    .getByRole('navigation', { name: 'Main navigation' })
    .getByRole('link', { name: label, exact: true })
    .click();
}

export async function makeDraft(page: Page, title: string, estimatedBudget = '12000') {
  await openNavigation(page, 'Purchase requests');
  await page.getByRole('link', { name: 'Create request' }).first().click();
  await page.getByLabel(/Request title/).fill(title);
  await page
    .getByLabel('Business justification')
    .fill('E2E testing a complete procurement approval workflow.');
  await page.getByLabel(/Department/).selectOption('1');
  await page.getByLabel('Estimated budget (€)').fill(estimatedBudget);
  await page.getByLabel(/Item name/).fill('Development laptops');
  await page.getByLabel(/Quantity/).fill('2');
  await page.getByLabel('Est. unit price (€)').fill('1100');
  await page.getByRole('button', { name: /Create draft request/ }).click();
  await expect(page).toHaveURL(/\/requests\/\d+$/);
  await expect(page.getByRole('heading', { name: title })).toBeVisible();
  const id = Number(new URL(page.url()).pathname.split('/').at(-1));
  return id;
}

/** Navigate through the client router without a full reload. Demo role is session-scoped; the API token remains memory-only. */
export async function navigateInApp(page: Page, pathname: string) {
  await page.evaluate((path) => {
    window.history.pushState(null, '', path);
    window.dispatchEvent(new PopStateEvent('popstate'));
  }, pathname);
}
