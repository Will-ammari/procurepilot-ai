<?php

namespace App\Services\Procurement;

use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DepartmentService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public function paginatedForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Department::query()
            ->withCount(['users', 'purchaseRequests'])
            ->where('organization_id', $user->organization_id)
            ->orderBy('name');

        $this->applyFilters($query, $filters);

        return $query->paginate(
            perPage: $this->perPage($filters)
        );
    }

    public function create(User $user, array $data): Department
    {
        return DB::transaction(function () use ($user, $data): Department {
            $department = Department::create([
                'organization_id' => $user->organization_id,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
            ]);

            return $department->loadCount(['users', 'purchaseRequests']);
        });
    }

    public function update(Department $department, array $data): Department
    {
        return DB::transaction(function () use ($department, $data): Department {
            $department->update($data);

            return $department->fresh()->loadCount(['users', 'purchaseRequests']);
        });
    }

    public function delete(Department $department): void
    {
        DB::transaction(function () use ($department): void {
            $department->delete();
        });
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
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
