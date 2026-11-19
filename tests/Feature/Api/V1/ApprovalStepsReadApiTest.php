<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApprovalStep;
use App\Models\Department;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalStepsReadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_steps_are_ordered_and_scoped_to_visible_request(): void
    {
        $org = Organization::factory()->create();
        $department = Department::factory()->create(['organization_id' => $org->id]);
        $requester = User::factory()->create([
            'organization_id' => $org->id, 'department_id' => $department->id, 'role' => User::ROLE_REQUESTER,
        ]);
        $request = PurchaseRequest::factory()->create([
            'organization_id' => $org->id, 'department_id' => $department->id, 'requester_id' => $requester->id,
        ]);
        ApprovalStep::create([
            'organization_id' => $org->id, 'purchase_request_id' => $request->id, 'sequence' => 2, 'approval_role' => ApprovalStep::ROLE_FINANCE, 'status' => ApprovalStep::STATUS_PENDING,
        ]);
        ApprovalStep::create([
            'organization_id' => $org->id, 'purchase_request_id' => $request->id, 'sequence' => 1, 'approval_role' => ApprovalStep::ROLE_MANAGER, 'status' => ApprovalStep::STATUS_PENDING,
        ]);
        Sanctum::actingAs($requester);
        $this->getJson("/api/v1/purchase-requests/{$request->id}/approval-steps")
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.sequence', 1)
            ->assertJsonPath('data.1.sequence', 2);
    }

    public function test_other_organization_cannot_read_approval_steps(): void
    {
        $org = Organization::factory()->create();
        $department = Department::factory()->create(['organization_id' => $org->id]);
        $requester = User::factory()->create(['organization_id' => $org->id, 'department_id' => $department->id]);
        $request = PurchaseRequest::factory()->create([
            'organization_id' => $org->id, 'department_id' => $department->id, 'requester_id' => $requester->id,
        ]);
        $otherOrg = Organization::factory()->create();
        $stranger = User::factory()->create(['organization_id' => $otherOrg->id, 'role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($stranger);
        $this->getJson("/api/v1/purchase-requests/{$request->id}/approval-steps")->assertForbidden();
    }
}
