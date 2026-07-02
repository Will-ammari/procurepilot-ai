<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexQuoteRequest;
use App\Http\Requests\Api\V1\StoreQuoteRequest;
use App\Http\Requests\Api\V1\UpdateQuoteRequest;
use App\Http\Resources\Api\V1\QuoteResource;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Services\Procurement\QuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class QuoteController extends Controller
{
    public function __construct(
        private readonly QuoteService $quoteService
    ) {}

    public function indexForPurchaseRequest(
        IndexQuoteRequest $request,
        PurchaseRequest $purchaseRequest
    ): AnonymousResourceCollection {
        $this->authorize('view', $purchaseRequest);

        $quotes = $this->quoteService->paginatedForPurchaseRequest(
            purchaseRequest: $purchaseRequest,
            user: $request->user(),
            filters: $request->validated()
        );

        return QuoteResource::collection($quotes);
    }

    public function storeForPurchaseRequest(
        StoreQuoteRequest $request,
        PurchaseRequest $purchaseRequest
    ): JsonResponse {
        $this->authorize('create', [Quote::class, $purchaseRequest]);

        $quote = $this->quoteService->createForPurchaseRequest(
            purchaseRequest: $purchaseRequest,
            user: $request->user(),
            data: $request->validated()
        );

        return (new QuoteResource($quote))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Quote $quote): QuoteResource
    {
        $this->authorize('view', $quote);

        return new QuoteResource(
            $quote->load(['purchaseRequest', 'vendor', 'items'])
        );
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): QuoteResource
    {
        $this->authorize('update', $quote);

        $quote = $this->quoteService->update(
            quote: $quote,
            data: $request->validated()
        );

        return new QuoteResource($quote);
    }

    public function destroy(Quote $quote): JsonResponse
    {
        $this->authorize('delete', $quote);

        $this->quoteService->delete($quote);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
