<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\QuoteComparisonResource;
use App\Models\PurchaseRequest;
use App\Services\Procurement\QuoteComparisonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteComparisonController extends Controller
{
    public function __construct(
        private readonly QuoteComparisonService $comparisons
    ) {}

    public function show(PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('compareQuotes', $purchaseRequest);

        $comparison = $this->comparisons->latest($purchaseRequest);

        return (new QuoteComparisonResource($comparison))->response();
    }

    public function generate(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('compareQuotes', $purchaseRequest);

        $comparison = $this->comparisons->generate($purchaseRequest, $request->user());

        return (new QuoteComparisonResource($comparison))
            ->response()
            ->setStatusCode(201);
    }
}
