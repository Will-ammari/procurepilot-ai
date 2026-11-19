import { beforeEach, describe, expect, it } from 'vitest';
import { demo, resetDemo } from './demo';
import type { PurchaseInput } from '../domain/types';
const sample: PurchaseInput = {
  title: 'Test laptops procurement',
  description: 'Hardware test',
  department_id: 1,
  estimated_budget: 12000,
  currency: 'EUR',
  priority: 'high',
  items: [{ name: 'Laptop', quantity: 6, expected_unit_price: 2000 }],
};
beforeEach(() => resetDemo());
describe('synthetic procurement workflow', () => {
  it('follows the same three-level approval thresholds as the Laravel service', async () => {
    const requester = demo.login('requester'),
      buyer = demo.login('procurement'),
      manager = demo.login('manager'),
      finance = demo.login('finance'),
      admin = demo.login('admin');
    const request = await demo.createRequest(requester, sample);
    await demo.submit(requester, request.id);
    await demo.createQuote(buyer, request.id, {
      vendor_id: 1,
      total_amount: 11700,
      currency: 'EUR',
      status: 'received',
    });
    await demo.createQuote(buyer, request.id, {
      vendor_id: 2,
      total_amount: 11500,
      currency: 'EUR',
      status: 'received',
    });
    await demo.generateComparison(buyer, request.id);
    const steps = await demo.sendForApproval(buyer, request.id);
    expect(steps.map((s) => s.approval_role)).toEqual(['manager', 'finance', 'admin']);
    await expect(demo.decide(requester, steps[0].id, 'approve', '')).rejects.toMatchObject({
      status: 403,
    });
    await expect(demo.decide(finance, steps[1].id, 'approve', '')).rejects.toMatchObject({
      status: 422,
    });
    await demo.decide(manager, steps[0].id, 'approve', 'Approved by department');
    await demo.decide(finance, steps[1].id, 'approve', 'Finance check');
    await demo.decide(admin, steps[2].id, 'approve', 'Final sign-off');
    expect((await demo.request(requester, request.id)).status).toBe('approved');
  });
  it('prevents invoice creation by procurement', async () => {
    const buyer = demo.login('procurement');
    await expect(
      demo.createInvoice(buyer, {
        invoice_number: 'TEST-1',
        invoice_date: '2026-11-19',
        purchase_request_id: 101,
        vendor_id: 1,
        subtotal: 100,
        currency: 'EUR',
      }),
    ).rejects.toMatchObject({ status: 403 });
  });
  it('applies visibility filters and pagination', async () => {
    const buyer = demo.login('procurement');
    const page = await demo.requests(buyer, { page: 2, per_page: 5 });
    expect(page.data).toHaveLength(5);
    expect(page.meta?.total).toBe(12);
    expect(
      (await demo.requests(buyer, { status: 'draft' })).data.every((v) => v.status === 'draft'),
    ).toBe(true);
  });
});
