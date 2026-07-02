<?php

namespace App\Services\Procurement;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class VendorService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public function paginatedForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Vendor::query()
            ->with('contacts')
            ->where('organization_id', $user->organization_id)
            ->latest();

        if ($user->isRequester()) {
            $query->where('status', Vendor::STATUS_ACTIVE);
        }

        $this->applyFilters($query, $filters);

        return $query->paginate(
            perPage: $this->perPage($filters)
        );
    }

    public function create(User $user, array $data): Vendor
    {
        return DB::transaction(function () use ($user, $data): Vendor {
            $contacts = $data['contacts'] ?? [];
            $vendorData = Arr::except($data, ['contacts']);

            $vendor = Vendor::create(array_merge($vendorData, [
                'organization_id' => $user->organization_id,
                'country' => strtoupper($vendorData['country'] ?? 'DE'),
                'default_currency' => strtoupper($vendorData['default_currency'] ?? 'EUR'),
                'status' => $vendorData['status'] ?? Vendor::STATUS_ACTIVE,
            ]));

            $this->replaceContacts($vendor, $contacts);

            return $vendor->load('contacts');
        });
    }

    public function update(Vendor $vendor, array $data): Vendor
    {
        return DB::transaction(function () use ($vendor, $data): Vendor {
            $contacts = $data['contacts'] ?? null;
            $vendorData = Arr::except($data, ['contacts']);

            if (array_key_exists('country', $vendorData) && $vendorData['country'] !== null) {
                $vendorData['country'] = strtoupper($vendorData['country']);
            }

            if (array_key_exists('default_currency', $vendorData) && $vendorData['default_currency'] !== null) {
                $vendorData['default_currency'] = strtoupper($vendorData['default_currency']);
            }

            $vendor->update($vendorData);

            if (is_array($contacts)) {
                $this->replaceContacts($vendor, $contacts);
            }

            return $vendor->fresh('contacts');
        });
    }

    private function replaceContacts(Vendor $vendor, array $contacts): void
    {
        $vendor->contacts()->delete();

        foreach ($contacts as $contact) {
            $vendor->contacts()->create([
                'name' => $contact['name'],
                'email' => $contact['email'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'role' => $contact['role'] ?? null,
            ]);
        }
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['country'])) {
            $query->where('country', strtoupper($filters['country']));
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhere('vat_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
    }

    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);

        if ($perPage < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($perPage, self::MAX_PER_PAGE);
    }
}
