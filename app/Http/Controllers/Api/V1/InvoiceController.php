<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInvoiceRequest;
use App\Http\Requests\Api\V1\UpdateInvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Invoice;
use App\Services\Procurement\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = $this->invoiceService->listForUser(
            user: $request->user(),
            filters: $request->validate([
                'status' => ['nullable', 'string'],
                'vendor_id' => ['nullable', 'integer'],
                'purchase_request_id' => ['nullable', 'integer'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ])
        );

        return InvoiceResource::collection($invoices);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);

        $invoice = $this->invoiceService->create(
            data: $request->validated(),
            user: $request->user()
        );

        return (new InvoiceResource($invoice))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource(
            $invoice->load(['vendor', 'purchaseRequest'])
        );
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): InvoiceResource
    {
        $this->authorize('update', $invoice);

        $invoice = $this->invoiceService->update(
            invoice: $invoice,
            data: $request->validated()
        );

        return new InvoiceResource($invoice);
    }

    public function markPaid(Invoice $invoice): InvoiceResource
    {
        $this->authorize('markPaid', $invoice);

        $invoice = $this->invoiceService->markPaid(
            invoice: $invoice,
            user: request()->user()
        );

        return new InvoiceResource($invoice);
    }
}
