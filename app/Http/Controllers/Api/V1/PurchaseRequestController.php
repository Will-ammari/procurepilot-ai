<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexPurchaseRequestRequest;
use App\Http\Requests\Api\V1\StorePurchaseRequestRequest;
use App\Http\Requests\Api\V1\UpdatePurchaseRequestRequest;
use App\Http\Resources\Api\V1\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use Illuminate\Http\Request;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestService $purchaseRequestService
    ) {}

    public function index(IndexPurchaseRequestRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PurchaseRequest::class);

        $purchaseRequests = $this->purchaseRequestService->paginatedForUser(
            user: $request->user(),
            filters: $request->validated()
        );

        return PurchaseRequestResource::collection($purchaseRequests);
    }

    public function store(StorePurchaseRequestRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseRequest::class);

        $purchaseRequest = $this->purchaseRequestService->create(
            user: $request->user(),
            data: $request->validated()
        );

        return (new PurchaseRequestResource($purchaseRequest))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('view', $purchaseRequest);

        return new PurchaseRequestResource(
            $purchaseRequest->load(['department', 'requester', 'items'])
        );
    }

    public function update(
        UpdatePurchaseRequestRequest $request,
        PurchaseRequest $purchaseRequest
    ): PurchaseRequestResource {
        $this->authorize('update', $purchaseRequest);

        $purchaseRequest = $this->purchaseRequestService->update(
            purchaseRequest: $purchaseRequest,
            data: $request->validated()
        );

        return new PurchaseRequestResource($purchaseRequest);
    }

    public function destroy(PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('delete', $purchaseRequest);

        $this->purchaseRequestService->delete($purchaseRequest);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function submit(Request $request, PurchaseRequest $purchaseRequest): PurchaseRequestResource
    {
        $this->authorize('submit', $purchaseRequest);

        $purchaseRequest = $this->purchaseRequestService->submit(
            purchaseRequest: $purchaseRequest,
            user: $request->user()
        );

        return new PurchaseRequestResource($purchaseRequest);
    }
}
