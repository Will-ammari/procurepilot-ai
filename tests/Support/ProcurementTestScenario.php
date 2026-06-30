<?php

namespace Tests\Support;

use App\Models\Department;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Hash;

class ProcurementTestScenario
{
    public Organization $organization;

    public Department $department;

    public User $admin;

    public User $requester;

    public User $manager;

    public User $finance;

    public User $procurement;

    public function __construct()
    {
        $this->organization = Organization::factory()->create([
            'name' => 'Acme Procurement GmbH',
            'country' => 'DE',
            'currency' => 'EUR',
            'vat_rate' => 19.00,
        ]);

        $this->department = Department::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Operations',
            'code' => 'OPS',
        ]);

        $this->admin = $this->user(User::ROLE_ADMIN, 'admin@example.test');
        $this->requester = $this->user(User::ROLE_REQUESTER, 'requester@example.test');
        $this->manager = $this->user(User::ROLE_MANAGER, 'manager@example.test');
        $this->finance = $this->user(User::ROLE_FINANCE, 'finance@example.test');
        $this->procurement = $this->user(User::ROLE_PROCUREMENT, 'procurement@example.test');
    }

    public function user(string $role, string $email): User
    {
        return User::factory()->create([
            'organization_id' => $this->organization->id,
            'department_id' => $this->department->id,
            'role' => $role,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
    }

    public function purchaseRequest(array $attributes = []): PurchaseRequest
    {
        $purchaseRequest = PurchaseRequest::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'department_id' => $this->department->id,
            'requester_id' => $this->requester->id,
            'title' => 'Procure laptops for operations team',
            'estimated_budget' => 2500.00,
            'currency' => 'EUR',
            'status' => PurchaseRequest::STATUS_DRAFT,
        ], $attributes));

        PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $purchaseRequest->id,
            'name' => 'Business laptop',
            'quantity' => 3,
            'expected_unit_price' => 800.00,
            'category' => 'hardware',
        ]);

        return $purchaseRequest;
    }

    public function vendor(array $attributes = []): Vendor
    {
        return Vendor::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'name' => 'Reliable Supplier GmbH '.fake()->unique()->numberBetween(100, 999),
            'status' => Vendor::STATUS_ACTIVE,
        ], $attributes));
    }
}
