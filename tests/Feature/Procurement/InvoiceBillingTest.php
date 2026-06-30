<?php

namespace Tests\Feature\Procurement;

use App\Models\Invoice;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ProcurementTestScenario;
use Tests\TestCase;

class InvoiceBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_user_can_create_invoice_with_calculated_vat_and_total(): void
    {
        $scenario = new ProcurementTestScenario();

        $purchaseRequest = $scenario->purchaseRequest([
            'status' => PurchaseRequest::STATUS_APPROVED,
        ]);

        $vendor = $scenario->vendor();

        $this->actingAs($scenario->finance)
            ->postJson('/api/v1/invoices', [
                'purchase_request_id' => $purchaseRequest->id,
                'vendor_id' => $vendor->id,
                'invoice_number' => 'INV-2026-0001',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'subtotal' => 1000.00,
                'vat_rate' => 19.00,
                'currency' => 'EUR',
            ])
            ->assertCreated()
            ->assertJsonPath('data.invoice_number', 'INV-2026-0001')
            ->assertJsonPath('data.vat_amount', 190)
            ->assertJsonPath('data.total', 1190);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => PurchaseRequest::STATUS_INVOICED,
        ]);
    }

    public function test_invoice_vendor_must_match_approved_quote_vendor_when_present(): void
    {
        $scenario = new ProcurementTestScenario();

        $approvedVendor = $scenario->vendor([
            'name' => 'Approved Supplier GmbH',
        ]);

        $otherVendor = $scenario->vendor([
            'name' => 'Wrong Supplier GmbH',
        ]);

        $purchaseRequest = $scenario->purchaseRequest([
            'status' => PurchaseRequest::STATUS_APPROVED,
        ]);

        $quote = Quote::factory()->create([
            'organization_id' => $scenario->organization->id,
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $approvedVendor->id,
        ]);

        $purchaseRequest->update([
            'approved_quote_id' => $quote->id,
        ]);

        $this->actingAs($scenario->finance)
            ->postJson('/api/v1/invoices', [
                'purchase_request_id' => $purchaseRequest->id,
                'vendor_id' => $otherVendor->id,
                'invoice_number' => 'INV-2026-0002',
                'invoice_date' => now()->toDateString(),
                'subtotal' => 500.00,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vendor_id']);
    }

    public function test_paid_invoice_cannot_be_updated(): void
    {
        $scenario = new ProcurementTestScenario();

        $invoice = Invoice::factory()->create([
            'organization_id' => $scenario->organization->id,
            'purchase_request_id' => $scenario->purchaseRequest([
                'status' => PurchaseRequest::STATUS_INVOICED,
            ])->id,
            'vendor_id' => $scenario->vendor()->id,
            'status' => Invoice::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $this->actingAs($scenario->finance)
            ->patchJson("/api/v1/invoices/{$invoice->id}", [
                'subtotal' => 750.00,
            ])
            ->assertForbidden();
    }
}
