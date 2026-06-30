<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Quote> */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        $purchaseRequest = PurchaseRequest::factory()->create();

        $vendor = Vendor::factory()->create([
            'organization_id' => $purchaseRequest->organization_id,
        ]);

        return [
            'organization_id' => $purchaseRequest->organization_id,
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'total_amount' => fake()->randomFloat(2, 500, 10000),
            'currency' => 'EUR',
            'delivery_days' => fake()->numberBetween(3, 30),
            'payment_terms' => 'Net 30',
            'warranty_months' => 12,
            'valid_until' => now()->addMonth()->toDateString(),
            'notes' => fake()->sentence(),
            'status' => Quote::STATUS_RECEIVED,
        ];
    }
}
