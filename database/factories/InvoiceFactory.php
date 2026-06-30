<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\PurchaseRequest;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $purchaseRequest = PurchaseRequest::factory()->create([
            'status' => PurchaseRequest::STATUS_APPROVED,
        ]);

        $vendor = Vendor::factory()->create([
            'organization_id' => $purchaseRequest->organization_id,
        ]);

        $subtotal = fake()->randomFloat(2, 100, 5000);
        $vatRate = 19.00;
        $vatAmount = round($subtotal * ($vatRate / 100), 2);

        return [
            'organization_id' => $purchaseRequest->organization_id,
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => strtoupper(fake()->bothify('INV-####-????')),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'subtotal' => $subtotal,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'total' => round($subtotal + $vatAmount, 2),
            'currency' => 'EUR',
            'status' => Invoice::STATUS_RECEIVED,
            'notes' => null,
        ];
    }
}
