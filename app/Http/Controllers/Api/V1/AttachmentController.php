<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttachmentRequest;
use App\Http\Requests\Api\V1\StoreAttachmentRequest;
use App\Http\Resources\Api\V1\AttachmentResource;
use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Services\Support\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachmentService
    ) {}

    public function index(IndexAttachmentRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Attachment::class);

        $attachments = $this->attachmentService->paginatedForUser(
            user: $request->user(),
            filters: $request->validated()
        );

        return AttachmentResource::collection($attachments);
    }

    public function storeForPurchaseRequest(
        StoreAttachmentRequest $request,
        PurchaseRequest $purchaseRequest
    ): JsonResponse {
        $this->authorize('uploadAttachment', $purchaseRequest);

        $attachment = $this->attachmentService->storeForAttachable(
            attachable: $purchaseRequest,
            user: $request->user(),
            file: $request->file('file')
        );

        return (new AttachmentResource($attachment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function storeForQuote(
        StoreAttachmentRequest $request,
        Quote $quote
    ): JsonResponse {
        $this->authorize('uploadAttachment', $quote);

        $attachment = $this->attachmentService->storeForAttachable(
            attachable: $quote,
            user: $request->user(),
            file: $request->file('file')
        );

        return (new AttachmentResource($attachment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function storeForInvoice(
        StoreAttachmentRequest $request,
        Invoice $invoice
    ): JsonResponse {
        $this->authorize('uploadAttachment', $invoice);

        $attachment = $this->attachmentService->storeForAttachable(
            attachable: $invoice,
            user: $request->user(),
            file: $request->file('file')
        );

        return (new AttachmentResource($attachment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Attachment $attachment): AttachmentResource
    {
        $this->authorize('view', $attachment);

        return new AttachmentResource($attachment->load(['uploadedBy']));
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);

        abort_unless(
            Storage::disk($attachment->disk)->exists($attachment->path),
            Response::HTTP_NOT_FOUND
        );

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name
        );
    }

    public function destroy(Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);

        $this->attachmentService->delete($attachment);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
