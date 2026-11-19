import { test, expect } from '@playwright/test';
import { makeDraft, navigateInApp, openNavigation, signInAs, switchToRole } from './helpers';

test('requester can create and submit a purchase request', async ({ page }) => {
  await signInAs(page, 'requester');
  await makeDraft(page, 'E2E laptop replacement');
  await expect(page.locator('.detail-meta .status')).toContainText('Draft');
  await page.getByRole('button', { name: 'Submit request' }).click();
  await expect(page.locator('.detail-meta .status')).toContainText('Submitted');
});

test('quotes, comparison and sequential authorisations work in the demo', async ({ page }) => {
  await signInAs(page, 'requester');
  await makeDraft(page, 'E2E sourcing decision', '12000');
  await page.getByRole('button', { name: 'Submit request' }).click();
  await expect(page.locator('.detail-meta .status')).toContainText('Submitted');
  // The requester cannot self-approve. Switch to the procurement/admin actor.
  await switchToRole(page, 'admin');
  await openNavigation(page, 'Purchase requests');
  await page.getByRole('link', { name: 'View E2E sourcing decision' }).click();

  for (const [supplier, amount] of [
    ['1', '9500'],
    ['2', '9100'],
  ] as const) {
    await page.getByRole('button', { name: 'Add quote' }).click();
    const modal = page.getByRole('dialog', { name: 'Record supplier quote' });
    await modal.getByLabel('Supplier').selectOption(supplier);
    await modal.getByLabel(/Quoted total/).fill(amount);
    await modal.getByRole('button', { name: 'Save quote' }).click();
    await expect(modal).not.toBeVisible();
  }

  await page.getByRole('button', { name: 'Generate comparison' }).click();
  await expect(page.getByText('LATEST COMPARISON')).toBeVisible();
  await expect(page.getByText('RECOMMENDED')).toBeVisible();
  await page.getByRole('button', { name: 'Send for approval' }).click();
  await expect(page.locator('.detail-meta .status')).toContainText(/pending approval/i);

  // The demo enforces sequential assignment: manager -> finance -> admin.
  for (const role of ['manager', 'finance', 'admin'] as const) {
    await switchToRole(page, role);
    await openNavigation(page, 'Purchase requests');
    await page.getByRole('link', { name: 'View E2E sourcing decision' }).click();
    await page.getByRole('button', { name: 'Approve', exact: true }).click();
    const dialog = page.getByRole('dialog', { name: 'Approve request' });
    await dialog.getByRole('button', { name: 'Confirm approval' }).click();
    await expect(dialog).not.toBeVisible();
  }
  await expect(page.locator('.detail-meta .status')).toContainText('Approved');
});

test('empty search and form validation do not fabricate records', async ({ page }) => {
  await signInAs(page);
  await openNavigation(page, 'Purchase requests');
  await page
    .getByRole('textbox', { name: 'Search request names…' })
    .fill('THIS-REQUEST-DOES-NOT-EXIST');
  await expect(page.getByRole('heading', { name: 'No matching requests' })).toBeVisible();
  await page.getByRole('link', { name: 'Create request' }).first().click();
  await page.getByLabel(/Request title/).fill('X');
  await page.getByRole('button', { name: 'Create draft request' }).click();
  await expect(
    page.getByRole('alert').filter({ hasText: 'Use at least 4 characters' }),
  ).toBeVisible();
});

test('missing request has a recoverable error state', async ({ page }) => {
  await signInAs(page);
  await navigateInApp(page, '/requests/999999');
  await expect(page.getByRole('heading', { name: 'Something went wrong' })).toBeVisible();
  await expect(page.getByText('Record not found')).toBeVisible();
  await expect(page.getByRole('button', { name: /Try again/ })).toBeVisible();
});
