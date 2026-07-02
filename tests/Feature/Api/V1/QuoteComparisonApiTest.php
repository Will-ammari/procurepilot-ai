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

class QuoteComparisonApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_can_generate_quote_comparison_and_recommend_best_offer(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);

        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);

        $firstQuote = $this->createQuote(
            $organization,
            $purchaseRequest,
            $this->createVendor($organization, 'Müller Office GmbH'),
            [
                'total_amount' => 16200,
                'delivery_days' => 7,
                'payment_terms' => 'Net 14',
                'warranty_months' => 12,
                'notes' => 'Delivery included. Installation not included.',
            ]
        );

        $secondQuote = $this->createQuote(
            $organization,
            $purchaseRequest,
            $this->createVendor($organization, 'Schneider Bürobedarf GmbH'),
            [
                'total_amount' => 14800,
                'delivery_days' => 10,
                'payment_terms' => 'Net 30',
                'warranty_months' => 24,
                'notes' => 'Includes delivery.',
            ]
        );

        $this->createAnalysis($firstQuote, ['installation not included']);
        $this->createAnalysis($secondQuote, []);

        Sanctum::actingAs($procurement);

        $response = $this->getJson("/api/v1/purchase-requests/{$purchaseRequest->id}/comparison");

        $response
            ->assertOk()
            ->assertJsonPath('data.purchase_request_id', $purchaseRequest->id)
            ->assertJsonPath('data.recommended_quote_id', $secondQuote->id)
            ->assertJsonPath('data.recommended_vendor', 'Schneider Bürobedarf GmbH')
            ->assertJsonCount(2, 'data.quotes')
            ->assertJsonPath('data.weights.total_amount', 35);

        $this->assertDatabaseHas('quote_comparisons', [
            'purchase_request_id' => $purchaseRequest->id,
            'recommended_quote_id' => $secondQuote->id,
            'generated_by_user_id' => $procurement->id,
        ]);
    }

    public function test_comparison_requires_at_least_two_quotes(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);

        $this->createQuote(
            $organization,
            $purchaseRequest,
            $this->createVendor($organization, 'Single Supplier GmbH')
        );

        Sanctum::actingAs($procurement);

        $this->getJson("/api/v1/purchase-requests/{$purchaseRequest->id}/comparison")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quotes']);
    }

    public function test_requester_cannot_generate_comparison(): void
    {
        $organization = $this->createOrganization();
        $department = $this->createDepartment($organization);
        $requester = $this->createUser($organization, User::ROLE_REQUESTER, $department);
        $purchaseRequest = $this->createPurchaseRequest($organization, $department, $requester);

        $this->createQuote($organization, $purchaseRequest, $this->createVendor($organization, 'A GmbH'));
        $this->createQuote($organization, $purchaseRequest, $this->createVendor($organization, 'B GmbH'));

        Sanctum::actingAs($requester);

        $this->getJson("/api/v1/purchase-requests/{$purchaseRequest->id}/comparison")
            ->assertForbidden();
    }

    public function test_user_cannot_compare_purchase_request_from_another_organization(): void
    {
        $organization = $this->createOrganization();
        $foreignOrganization = $this->createOrganization('Foreign GmbH');

        $procurement = $this->createUser($organization, User::ROLE_PROCUREMENT);
        $foreignDepartment = $this->createDepartment($foreignOrganization);
        $foreignRequester = $this->createUser($foreignOrganization, User::ROLE_REQUESTER, $foreignDepartment);
        $foreignPurchaseRequest = $this->createPurchaseRequest($foreignOrganization, $foreignDepartment, $foreignRequester);

        $this->createQuote($foreignOrganization, $foreignPurchaseRequest, $this->createVendor($foreignOrganization, 'Foreign A GmbH'));
        $this->createQuote($foreignOrganization, $foreignPurchaseRequest, $this->createVendor($foreignOrganization, 'Foreign B GmbH'));

        Sanctum::actingAs($procurement);

        $this->getJson("/api/v1/purchase-requests/{$foreignPurchaseRequest->id}/comparison")
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

    private function createVendor(Organization $organization, string $name): Vendor
    {
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
            'total_amount' => 15000,
            'currency' => 'EUR',
            'delivery_days' => 14,
            'payment_terms' => 'Net 30',
            'warranty_months' => 12,
            'valid_until' => now()->addMonth()->toDateString(),
            'notes' => 'Includes delivery.',
            'status' => Quote::STATUS_ANALYZED,
        ], $overrides));
    }

    private function createAnalysis(Quote $quote, array $hiddenCosts): QuoteAnalysis
    {
        return QuoteAnalysis::create([
            'quote_id' => $quote->id,
            'raw_text' => 'Quote snapshot',
            'summary' => 'Generated analysis summary.',
            'extracted_terms' => [
                'vendor_name' => $quote->vendor->name,
                'total_price' => (float) $quote->total_amount,
                'currency' => $quote->currency,
            ],
            'hidden_costs' => $hiddenCosts,
            'risk_notes' => [],
            'confidence_score' => 0.90,
            'model_name' => 'local-deterministic-quote-analyzer-v1',
            'status' => QuoteAnalysis::STATUS_COMPLETED,
        ]);
    }
}
