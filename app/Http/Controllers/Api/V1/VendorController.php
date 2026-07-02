<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexVendorRequest;
use App\Http\Requests\Api\V1\StoreVendorRequest;
use App\Http\Requests\Api\V1\UpdateVendorRequest;
use App\Http\Resources\Api\V1\VendorResource;
use App\Models\Vendor;
use App\Services\Procurement\VendorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VendorController extends Controller
{
    public function __construct(
        private readonly VendorService $vendorService
    ) {}

    public function index(IndexVendorRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Vendor::class);

        $vendors = $this->vendorService->paginatedForUser(
            user: $request->user(),
            filters: $request->validated()
        );

        return VendorResource::collection($vendors);
    }

    public function store(StoreVendorRequest $request): JsonResponse
    {
        $this->authorize('create', Vendor::class);

        $vendor = $this->vendorService->create(
            user: $request->user(),
            data: $request->validated()
        );

        return (new VendorResource($vendor))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Vendor $vendor): VendorResource
    {
        $this->authorize('view', $vendor);

        return new VendorResource($vendor->load('contacts'));
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor): VendorResource
    {
        $this->authorize('update', $vendor);

        $vendor = $this->vendorService->update(
            vendor: $vendor,
            data: $request->validated()
        );

        return new VendorResource($vendor);
    }

    public function destroy(Vendor $vendor): JsonResponse
    {
        $this->authorize('delete', $vendor);

        $vendor->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
