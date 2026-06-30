<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vendor> */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->company(),
            'legal_name' => fake()->company().' GmbH',
            'vat_id' => 'DE'.fake()->numerify('#########'),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'website' => 'https://'.fake()->domainName(),
            'address' => fake()->address(),
            'country' => 'DE',
            'default_currency' => 'EUR',
            'status' => Vendor::STATUS_ACTIVE,
        ];
    }
}
