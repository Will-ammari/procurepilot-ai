<?php

namespace Tests\Feature\Procurement;

use App\Jobs\Procurement\RecordPurchaseRequestSubmitted;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Support\ProcurementTestScenario;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_submit_own_draft_purchase_request(): void
    {
        Bus::fake();

        $scenario = new ProcurementTestScenario();
        $purchaseRequest = $scenario->purchaseRequest();

        $this->actingAs($scenario->requester)
            ->postJson("/api/v1/purchase-requests/{$purchaseRequest->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseRequest::STATUS_SUBMITTED);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => PurchaseRequest::STATUS_SUBMITTED,
        ]);

        Bus::assertDispatched(RecordPurchaseRequestSubmitted::class);
    }

    public function test_requester_cannot_update_submitted_purchase_request(): void
    {
        $scenario = new ProcurementTestScenario();

        $purchaseRequest = $scenario->purchaseRequest([
            'status' => PurchaseRequest::STATUS_SUBMITTED,
        ]);

        $this->actingAs($scenario->requester)
            ->patchJson("/api/v1/purchase-requests/{$purchaseRequest->id}", [
                'title' => 'Unauthorized title change',
            ])
            ->assertForbidden();
    }

    public function test_viewer_can_read_but_cannot_create_purchase_requests(): void
    {
        $scenario = new ProcurementTestScenario();

        $viewer = User::factory()->create([
            'organization_id' => $scenario->organization->id,
            'department_id' => $scenario->department->id,
            'role' => User::ROLE_VIEWER,
        ]);

        $scenario->purchaseRequest();

        $this->actingAs($viewer)
            ->getJson('/api/v1/purchase-requests')
            ->assertOk();

        $this->actingAs($viewer)
            ->postJson('/api/v1/purchase-requests', [
                'department_id' => $scenario->department->id,
                'title' => 'Should not be created',
                'items' => [[
                    'name' => 'Laptop',
                    'quantity' => 1,
                ]],
            ])
            ->assertForbidden();
    }
}   
