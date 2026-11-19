import { test, expect } from '@playwright/test';
import { navigateInApp, openNavigation, signInAs } from './helpers';

test('finance role can create and settle an invoice', async ({ page }) => {
  await signInAs(page, 'finance');
  await openNavigation(page, 'Invoices');
  await page.getByRole('button', { name: 'New invoice' }).click();
  const modal = page.getByRole('dialog', { name: 'Create invoice' });
  await modal.getByLabel(/Invoice number/).fill('INV-E2E-2026-0001');
  await modal.getByLabel('Purchase request').selectOption('103');
  await modal.getByLabel('Supplier').selectOption('4');
  await modal.getByLabel(/Subtotal/).fill('2100');
  await modal.getByRole('button', { name: 'Create invoice' }).click();
  await expect(modal).not.toBeVisible();
  const row = page.getByRole('row').filter({ hasText: 'INV-E2E-2026-0001' });
  await expect(row).toContainText('Received');
  page.once('dialog', (dialog) => dialog.accept());
  await row.getByRole('button', { name: 'Mark paid' }).click();
  await expect(row).toContainText('Paid');
});

test('viewer cannot see or access protected write affordances', async ({ page }) => {
  await signInAs(page, 'viewer');
  await openNavigation(page, 'Purchase requests');
  await expect(page.getByRole('link', { name: 'Create request' })).toHaveCount(0);
  await navigateInApp(page, '/requests/new');
  await expect(page.getByText('Your role cannot create purchase requests.')).toBeVisible();
  await openNavigation(page, 'Invoices');
  await expect(page.getByRole('button', { name: 'New invoice' })).toHaveCount(0);
  await expect(
    page
      .getByRole('navigation', { name: 'Main navigation' })
      .getByRole('link', { name: 'Activity log' }),
  ).toHaveCount(0);
  // This verifies *demo visibility*, not Laravel server-side authorisation.
});
