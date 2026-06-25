<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApprovalDecisionRequest;
use App\Http\Requests\Api\V1\RejectApprovalStepRequest;
use App\Http\Resources\Api\V1\ApprovalStepResource;
use App\Models\ApprovalStep;
use App\Models\PurchaseRequest;
use App\Services\Procurement\ApprovalWorkflowService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ApprovalWorkflowController extends Controller
{
    public function __construct(
        private readonly ApprovalWorkflowService $approvalWorkflowService
    ) {
    }

    public function sendForApproval(PurchaseRequest $purchaseRequest): AnonymousResourceCollection
    {
        $this->authorize('sendForApproval', $purchaseRequest);

        $steps = $this->approvalWorkflowService->sendForApproval(
            purchaseRequest: $purchaseRequest,
            user: request()->user()
        );

        return ApprovalStepResource::collection($steps);
    }

    public function approve(
        ApprovalDecisionRequest $request,
        ApprovalStep $approvalStep
    ): ApprovalStepResource {
        $this->authorize('approve', $approvalStep);

        $approvalStep = $this->approvalWorkflowService->approve(
            approvalStep: $approvalStep,
            user: $request->user(),
            comment: $request->validated('comment')
        );

        return new ApprovalStepResource($approvalStep);
    }

    public function reject(
        RejectApprovalStepRequest $request,
        ApprovalStep $approvalStep
    ): ApprovalStepResource {
        $this->authorize('reject', $approvalStep);

        $approvalStep = $this->approvalWorkflowService->reject(
            approvalStep: $approvalStep,
            user: $request->user(),
            comment: $request->validated('comment')
        );

        return new ApprovalStepResource($approvalStep);
    }
}
