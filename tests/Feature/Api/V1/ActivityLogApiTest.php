<?php

namespace Tests\Feature\Api\V1;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_request_creation_writes_activity_log(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);

        Sanctum::actingAs($requester);

        $response = $this->postJson('/api/v1/purchase-requests', [
            'department_id' => $department->id,
            'title' => 'New CAD workstation',
            'description' => 'Engineering needs a new CAD workstation.',
            'estimated_budget' => 2400,
            'currency' => 'EUR',
            'priority' => PurchaseRequest::PRIORITY_NORMAL,
            'items' => [
                [
                    'name' => 'CAD workstation',
                    'quantity' => 1,
                    'expected_unit_price' => 2400,
                    'category' => 'IT equipment',
                ],
            ],
        ]);

        $response->assertCreated();

        $purchaseRequestId = $response->json('data.id');

        $this->assertDatabaseHas('activity_logs', [
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'event' => ActivityLog::EVENT_PURCHASE_REQUEST_CREATED,
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $purchaseRequestId,
        ]);
    }

    public function test_purchase_request_submit_writes_activity_log(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);
        $purchaseRequest->items()->create([
            'name' => 'Laptop',
            'quantity' => 2,
            'expected_unit_price' => 1500,
            'category' => 'IT equipment',
        ]);

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseRequest::STATUS_SUBMITTED);

        $this->assertDatabaseHas('activity_logs', [
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'event' => ActivityLog::EVENT_PURCHASE_REQUEST_SUBMITTED,
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $purchaseRequest->id,
        ]);
    }

    public function test_procurement_can_list_activity_logs_for_own_organization(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);

        ActivityLog::create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'event' => ActivityLog::EVENT_PURCHASE_REQUEST_CREATED,
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $purchaseRequest->id,
            'metadata' => [
                'title' => $purchaseRequest->title,
            ],
        ]);

        Sanctum::actingAs($procurement);

        $this->getJson('/api/v1/activity-logs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', ActivityLog::EVENT_PURCHASE_REQUEST_CREATED)
            ->assertJsonPath('data.0.user.id', $requester->id);
    }

    public function test_activity_logs_can_be_filtered_by_event(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);

        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $finance = $this->createUser($organization, User::ROLE_FINANCE);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);

        ActivityLog::create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'event' => ActivityLog::EVENT_PURCHASE_REQUEST_CREATED,
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $purchaseRequest->id,
            'metadata' => [],
        ]);

        ActivityLog::create([
            'organization_id' => $organization->id,
            'user_id' => $requester->id,
            'event' => ActivityLog::EVENT_PURCHASE_REQUEST_SUBMITTED,
            'subject_type' => PurchaseRequest::class,
            'subject_id' => $purchaseRequest->id,
            'metadata' => [],
        ]);

        Sanctum::actingAs($finance);

        $this->getJson('/api/v1/activity-logs?event='.ActivityLog::EVENT_PURCHASE_REQUEST_SUBMITTED)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', ActivityLog::EVENT_PURCHASE_REQUEST_SUBMITTED);
    }

    public function test_requester_cannot_list_activity_logs(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);

        Sanctum::actingAs($requester);

        $this->getJson('/api/v1/activity-logs')
            ->assertForbidden();
    }

    public function test_user_cannot_view_activity_log_from_another_organization(): void
    {
        $organization = $this->createOrganization();
        $foreignOrganization = $this->createOrganization('Foreign GmbH');

        $admin = $this->createUser($organization, User::ROLE_ADMIN);
        $foreignUser = $this->createUser($foreignOrganization, User::ROLE_ADMIN);

        $activityLog = ActivityLog::create([
            'organization_id' => $foreignOrganization->id,
            'user_id' => $foreignUser->id,
            'event' => ActivityLog::EVENT_PURCHASE_REQUEST_CREATED,
            'metadata' => [],
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/activity-logs/{$activityLog->id}")
            ->assertForbidden();
    }

    private function createOrganization(string $name = 'Berlin Mittelstand GmbH'): Organization
    {
        return Organization::create([
            'name' => $name,
            'country' => 'DE',
            'currency' => 'EUR',
            'vat_rate' => 19.00,
        ]);
    }

    private function createDepartment(Organization $organization): Department
    {
        return Department::create([
            'organization_id' => $organization->id,
            'name' => 'Engineering',
            'code' => 'ENG',
            'description' => 'Engineering department',
        ]);
    }

    private function createUser(
        Organization $organization,
        string $role,
        ?Department $department = null
    ): User {
        return User::create([
            'organization_id' => $organization->id,
            'department_id' => $department?->id,
            'name' => ucfirst($role).' User',
            'email' => $role.uniqid('', true).'@procurepilot.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    private function createPurchaseRequest(
        Organization $organization,
        Department $department,
        User $requester,
        string $status = PurchaseRequest::STATUS_DRAFT
    ): PurchaseRequest {
        return PurchaseRequest::create([
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'requester_id' => $requester->id,
            'title' => 'Test purchase request',
            'description' => 'Test description',
            'needed_by_date' => now()->addMonth()->toDateString(),
            'estimated_budget' => 5000,
            'currency' => 'EUR',
            'priority' => PurchaseRequest::PRIORITY_NORMAL,
            'status' => $status,
        ]);
    }
}
