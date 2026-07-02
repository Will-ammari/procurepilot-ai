<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VendorScorecardResource;
use App\Models\Vendor;
use App\Services\Procurement\VendorScorecardService;
use Illuminate\Http\JsonResponse;

class VendorScorecardController extends Controller
{
    public function __construct(
        private readonly VendorScorecardService $vendorScorecardService
    ) {}

    public function show(Vendor $vendor): JsonResponse
    {
        $this->authorize('view', $vendor);

        $scorecard = $this->vendorScorecardService->calculate(
            vendor: $vendor,
            user: request()->user()
        );

        return (new VendorScorecardResource($scorecard))
            ->response()
            ->setStatusCode(200);
    }
}
