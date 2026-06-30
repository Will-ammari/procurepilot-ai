<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseRequest> */
class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    public function definition(): array
    {
        $organization = Organization::factory()->create();

        $department = Department::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $requester = User::factory()->create([
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'role' => User::ROLE_REQUESTER,
        ]);

        return [
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'requester_id' => $requester->id,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'needed_by_date' => now()->addWeeks(2)->toDateString(),
            'estimated_budget' => fake()->randomFloat(2, 500, 15000),
            'currency' => 'EUR',
            'priority' => PurchaseRequest::PRIORITY_NORMAL,
            'status' => PurchaseRequest::STATUS_DRAFT,
        ];
    }
}
