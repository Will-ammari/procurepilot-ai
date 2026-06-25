<?php

namespace App\Services\Procurement;

use App\Models\Invoice;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function listForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with(['vendor', 'purchaseRequest'])
            ->where('organization_id', $user->organization_id)
            ->latest();

        if ($user->isRequester()) {
            $query->whereHas('purchaseRequest', function ($query) use ($user): void {
                $query->where('requester_id', $user->id);
            });
        }

        if ($user->isManager()) {
            $query->whereHas('purchaseRequest', function ($query) use ($user): void {
                $query->where('department_id', $user->department_id);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['vendor_id'])) {
            $query->where('vendor_id', $filters['vendor_id']);
        }

        if (! empty($filters['purchase_request_id'])) {
            $query->where('purchase_request_id', $filters['purchase_request_id']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function create(array $data, User $user): Invoice
    {
        return DB::transaction(function () use ($data, $user): Invoice {
            $purchaseRequest = PurchaseRequest::query()
                ->where('organization_id', $user->organization_id)
                ->findOrFail($data['purchase_request_id']);

            $vendor = Vendor::query()
                ->where('organization_id', $user->organization_id)
                ->findOrFail($data['vendor_id']);

            $this->ensurePurchaseRequestCanReceiveInvoice($purchaseRequest);
            $this->ensureVendorMatchesApprovedQuote($purchaseRequest, $vendor);

            $financials = $this->calculateFinancials(
                subtotal: (float) $data['subtotal'],
                vatRate: array_key_exists('vat_rate', $data) && $data['vat_rate'] !== null
                    ? (float) $data['vat_rate']
                    : $this->resolveDefaultVatRate($purchaseRequest)
            );

            $invoice = Invoice::create([
                'organization_id' => $user->organization_id,
                'purchase_request_id' => $purchaseRequest->id,
                'vendor_id' => $vendor->id,
                'invoice_number' => $data['invoice_number'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'subtotal' => $financials['subtotal'],
                'vat_rate' => $financials['vat_rate'],
                'vat_amount' => $financials['vat_amount'],
                'total' => $financials['total'],
                'currency' => strtoupper($data['currency'] ?? $purchaseRequest->currency ?? 'EUR'),
                'status' => Invoice::STATUS_RECEIVED,
                'notes' => $data['notes'] ?? null,
            ]);

            if (in_array($purchaseRequest->status, [
                PurchaseRequest::STATUS_APPROVED,
                PurchaseRequest::STATUS_ORDERED,
            ], true)) {
                $purchaseRequest->update([
                    'status' => PurchaseRequest::STATUS_INVOICED,
                ]);
            }

            return $invoice->load(['vendor', 'purchaseRequest']);
        });
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data): Invoice {
            $invoice->refresh();

            if ($invoice->isPaid() || $invoice->isCancelled()) {
                throw ValidationException::withMessages([
                    'invoice' => ['Paid or cancelled invoices cannot be updated.'],
                ]);
            }

            $subtotal = array_key_exists('subtotal', $data)
                ? (float) $data['subtotal']
                : (float) $invoice->subtotal;

            $vatRate = array_key_exists('vat_rate', $data) && $data['vat_rate'] !== null
                ? (float) $data['vat_rate']
                : (float) $invoice->vat_rate;

            $financials = $this->calculateFinancials($subtotal, $vatRate);

            $invoice->update([
                'invoice_number' => $data['invoice_number'] ?? $invoice->invoice_number,
                'invoice_date' => $data['invoice_date'] ?? $invoice->invoice_date,
                'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $invoice->due_date,
                'subtotal' => $financials['subtotal'],
                'vat_rate' => $financials['vat_rate'],
                'vat_amount' => $financials['vat_amount'],
                'total' => $financials['total'],
                'currency' => strtoupper($data['currency'] ?? $invoice->currency),
                'status' => $data['status'] ?? $invoice->status,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $invoice->notes,
            ]);

            return $invoice->load(['vendor', 'purchaseRequest']);
        });
    }

    public function markPaid(Invoice $invoice, User $user): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice->refresh();

            if ($invoice->isPaid()) {
                throw ValidationException::withMessages([
                    'invoice' => ['Invoice is already paid.'],
                ]);
            }

            if ($invoice->isCancelled()) {
                throw ValidationException::withMessages([
                    'invoice' => ['Cancelled invoices cannot be marked as paid.'],
                ]);
            }

            $invoice->update([
                'status' => Invoice::STATUS_PAID,
                'paid_at' => now(),
            ]);

            $invoice->purchaseRequest->update([
                'status' => PurchaseRequest::STATUS_PAID,
            ]);

            return $invoice->load(['vendor', 'purchaseRequest']);
        });
    }

    private function calculateFinancials(float $subtotal, float $vatRate): array
    {
        $vatAmount = round($subtotal * ($vatRate / 100), 2);
        $total = round($subtotal + $vatAmount, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'vat_rate' => round($vatRate, 2),
            'vat_amount' => $vatAmount,
            'total' => $total,
        ];
    }

    private function resolveDefaultVatRate(PurchaseRequest $purchaseRequest): float
    {
        return (float) ($purchaseRequest->organization?->vat_rate ?? 19.00);
    }

    private function ensurePurchaseRequestCanReceiveInvoice(PurchaseRequest $purchaseRequest): void
    {
        if (! in_array($purchaseRequest->status, [
            PurchaseRequest::STATUS_APPROVED,
            PurchaseRequest::STATUS_ORDERED,
            PurchaseRequest::STATUS_INVOICED,
        ], true)) {
            throw ValidationException::withMessages([
                'purchase_request' => ['Only approved, ordered, or already invoiced purchase requests can receive invoices.'],
            ]);
        }
    }

    private function ensureVendorMatchesApprovedQuote(PurchaseRequest $purchaseRequest, Vendor $vendor): void
    {
        if ($purchaseRequest->approved_quote_id === null) {
            return;
        }

        $approvedQuote = $purchaseRequest->approvedQuote()->first();

        if ($approvedQuote === null) {
            return;
        }

        if ($approvedQuote->vendor_id !== $vendor->id) {
            throw ValidationException::withMessages([
                'vendor_id' => ['Invoice vendor must match the approved quote vendor.'],
            ]);
        }
    }
}
