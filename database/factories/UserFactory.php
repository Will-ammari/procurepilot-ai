<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'department_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_REQUESTER,
            'remember_token' => Str::random(10),
        ];
    }

    public function forOrganization(
        ?Organization $organization = null,
        ?Department $department = null
    ): static {
        return $this->state(function () use ($organization, $department): array {
            $organization ??= Organization::factory()->create();

            $department ??= Department::factory()->create([
                'organization_id' => $organization->id,
            ]);

            return [
                'organization_id' => $organization->id,
                'department_id' => $department->id,
            ];
        });
    }

    public function role(string $role): static
    {
        return $this->state(fn (): array => [
            'role' => $role,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
