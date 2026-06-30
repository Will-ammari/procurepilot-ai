<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Department> */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->unique()->randomElement([
                'Engineering',
                'Operations',
                'Finance',
                'Procurement',
            ]).' '.fake()->numberBetween(1, 9999),
            'code' => strtoupper(fake()->bothify('DEP-###')),
        ];
    }
}
