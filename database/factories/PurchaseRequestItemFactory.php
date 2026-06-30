<?php

namespace Database\Factories;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseRequestItem> */
class PurchaseRequestItemFactory extends Factory
{
    protected $model = PurchaseRequestItem::class;

    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'quantity' => fake()->numberBetween(1, 5),
            'expected_unit_price' => fake()->randomFloat(2, 50, 500),
            'category' => fake()->randomElement([
                'hardware',
                'software',
                'services',
            ]),
        ];
    }
}
