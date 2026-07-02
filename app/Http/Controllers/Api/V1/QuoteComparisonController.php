<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\QuoteComparisonResource;
use App\Models\PurchaseRequest;
use App\Services\Procurement\QuoteComparisonService;
use Illuminate\Http\JsonResponse;

class QuoteComparisonController extends Controller
{
    public function __construct(
        private readonly QuoteComparisonService $quoteComparisonService
    ) {}

    public function show(PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('compareQuotes', $purchaseRequest);

        $comparison = $this->quoteComparisonService->generate(
            purchaseRequest: $purchaseRequest,
            user: request()->user()
        );

        return (new QuoteComparisonResource($comparison))
            ->response()
            ->setStatusCode(200);
    }
}
