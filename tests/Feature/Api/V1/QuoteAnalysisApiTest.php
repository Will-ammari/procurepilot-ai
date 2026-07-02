<?php

namespace Tests\Feature\Api\V1;

use App\Models\Department;
use App\Models\Organization;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\QuoteAnalysis;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteAnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_can_analyze_quote(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $vendor = $this->createVendor($organization, 'Schneider Bürobedarf GmbH');

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);
        $quote = $this->createQuote($organization, $purchaseRequest, $vendor, [
            'total_amount' => 14800,
            'delivery_days' => 10,
            'payment_terms' => 'Net 30',
            'warranty_months' => 24,
            'valid_until' => now()->addMonth()->toDateString(),
            'notes' => 'Includes delivery. Shipping not included for remote locations.',
        ]);

        Sanctum::actingAs($procurement);

        $response = $this->postJson("/api/v1/quotes/{$quote->id}/analyze");

        $response
            ->assertOk()
            ->assertJsonPath('data.quote_id', $quote->id)
            ->assertJsonPath('data.status', QuoteAnalysis::STATUS_COMPLETED)
            ->assertJsonPath('data.extracted_terms.vendor_name', 'Schneider Bürobedarf GmbH')
            ->assertJsonPath('data.extracted_terms.currency', 'EUR')
            ->assertJsonPath('data.extracted_terms.delivery_time_days', 10)
            ->assertJsonPath('data.hidden_costs.0', 'shipping not included')
            ->assertJsonPath('data.model_name', 'local-deterministic-quote-analyzer-v1');

        $this->assertDatabaseHas('quote_analyses', [
            'quote_id' => $quote->id,
            'status' => QuoteAnalysis::STATUS_COMPLETED,
        ]);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_ANALYZED,
        ]);
    }

    public function test_quote_analysis_can_be_viewed_after_generation(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $vendor = $this->createVendor($organization);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);
        $quote = $this->createQuote($organization, $purchaseRequest, $vendor);

        Sanctum::actingAs($procurement);

        $this->postJson("/api/v1/quotes/{$quote->id}/analyze")->assertOk();

        $response = $this->getJson("/api/v1/quotes/{$quote->id}/analysis");

        $response
            ->assertOk()
            ->assertJsonPath('data.quote_id', $quote->id)
            ->assertJsonPath('data.status', QuoteAnalysis::STATUS_COMPLETED);
    }

    public function test_requester_can_view_analysis_for_own_purchase_request_but_cannot_run_it(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $vendor = $this->createVendor($organization);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);
        $quote = $this->createQuote($organization, $purchaseRequest, $vendor);

        Sanctum::actingAs($procurement);
        $this->postJson("/api/v1/quotes/{$quote->id}/analyze")->assertOk();

        Sanctum::actingAs($requester);

        $this->postJson("/api/v1/quotes/{$quote->id}/analyze")
            ->assertForbidden();

        $this->getJson("/api/v1/quotes/{$quote->id}/analysis")
            ->assertOk()
            ->assertJsonPath('data.quote_id', $quote->id);
    }

    public function test_user_cannot_view_analysis_from_another_organization(): void
    {
        $organization = $this->createOrganization();
        $foreignOrganization = $this->createOrganization('Hamburg External GmbH');

        $admin = $this->createUser($organization, User::ROLE_ADMIN);

        $foreignDepartment = $this->createDepartment($foreignOrganization);
        $foreignRequester = $this->createUser($foreignOrganization, User::ROLE_REQUESTER, $foreignDepartment);
        $foreignVendor = $this->createVendor($foreignOrganization);
        $foreignPurchaseRequest = $this->createPurchaseRequest($foreignOrganization, $foreignDepartment, $foreignRequester);
        $foreignQuote = $this->createQuote($foreignOrganization, $foreignPurchaseRequest, $foreignVendor);

        QuoteAnalysis::create([
            'quote_id' => $foreignQuote->id,
            'raw_text' => 'Foreign quote snapshot',
            'summary' => 'Foreign analysis summary.',
            'extracted_terms' => ['vendor_name' => $foreignVendor->name],
            'hidden_costs' => [],
            'risk_notes' => [],
            'confidence_score' => 0.90,
            'model_name' => 'local-deterministic-quote-analyzer-v1',
            'status' => QuoteAnalysis::STATUS_COMPLETED,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/quotes/{$foreignQuote->id}/analysis")
            ->assertForbidden();
    }

    public function test_analysis_can_be_regenerated_for_same_quote(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $vendor = $this->createVendor($organization);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);
        $quote = $this->createQuote($organization, $purchaseRequest, $vendor, [
            'notes' => 'Shipping not included.',
        ]);

        Sanctum::actingAs($procurement);

        $firstResponse = $this->postJson("/api/v1/quotes/{$quote->id}/analyze");
        $firstResponse->assertOk();

        $analysisId = $firstResponse->json('data.id');

        $quote->update([
            'notes' => 'Delivery included. Installation not included.',
        ]);

        $secondResponse = $this->postJson("/api/v1/quotes/{$quote->id}/analyze");

        $secondResponse
            ->assertOk()
            ->assertJsonPath('data.id', $analysisId)
            ->assertJsonPath('data.hidden_costs.0', 'installation not included');

        $this->assertSame(1, QuoteAnalysis::where('quote_id', $quote->id)->count());
    }

    public function test_analysis_returns_not_found_when_not_generated_yet(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $vendor = $this->createVendor($organization);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);
        $quote = $this->createQuote($organization, $purchaseRequest, $vendor);

        Sanctum::actingAs($procurement);

        $this->getJson("/api/v1/quotes/{$quote->id}/analysis")
            ->assertNotFound();
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

    private function createDepartment(
        Organization $organization,
        string $name = 'Engineering',
        ?string $code = 'ENG'
    ): Department {
        return Department::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'code' => $code,
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

    private function createVendor(
        Organization $organization,
        string $name = 'Müller Office GmbH'
    ): Vendor {
        return Vendor::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'legal_name' => $name,
            'country' => 'DE',
            'default_currency' => 'EUR',
            'status' => Vendor::STATUS_ACTIVE,
        ]);
    }

    private function createPurchaseRequest(
        Organization $organization,
        Department $department,
        User $requester
    ): PurchaseRequest {
        return PurchaseRequest::create([
            'organization_id' => $organization->id,
            'department_id' => $department->id,
            'requester_id' => $requester->id,
            'title' => '12 laptops for engineering team',
            'description' => 'Engineering team needs new development laptops.',
            'needed_by_date' => now()->addMonth()->toDateString(),
            'estimated_budget' => 18000,
            'currency' => 'EUR',
            'priority' => PurchaseRequest::PRIORITY_NORMAL,
            'status' => PurchaseRequest::STATUS_QUOTES_RECEIVED,
        ]);
    }

    private function createQuote(
        Organization $organization,
        PurchaseRequest $purchaseRequest,
        Vendor $vendor,
        array $overrides = []
    ): Quote {
        return Quote::create(array_merge([
            'organization_id' => $organization->id,
            'purchase_request_id' => $purchaseRequest->id,
            'vendor_id' => $vendor->id,
            'total_amount' => 14800,
            'currency' => 'EUR',
            'delivery_days' => 10,
            'payment_terms' => 'Net 30',
            'warranty_months' => 24,
            'valid_until' => now()->addMonth()->toDateString(),
            'notes' => 'Includes delivery.',
            'status' => Quote::STATUS_RECEIVED,
        ], $overrides));
    }
}
