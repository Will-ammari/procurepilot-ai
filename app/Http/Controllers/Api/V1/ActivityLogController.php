<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexActivityLogRequest;
use App\Http\Resources\Api\V1\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityLogController extends Controller
{
    public function index(IndexActivityLogRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ActivityLog::class);

        $filters = $request->validated();

        $activityLogs = ActivityLog::query()
            ->with('user')
            ->where('organization_id', $request->user()->organization_id)
            ->when($filters['event'] ?? null, function (Builder $query, string $event): void {
                $query->where('event', $event);
            })
            ->when($filters['user_id'] ?? null, function (Builder $query, int|string $userId): void {
                $query->where('user_id', $userId);
            })
            ->when($filters['subject_type'] ?? null, function (Builder $query, string $subjectType): void {
                $query->where('subject_type', $subjectType);
            })
            ->when($filters['subject_id'] ?? null, function (Builder $query, int|string $subjectId): void {
                $query->where('subject_id', $subjectId);
            })
            ->when($filters['from'] ?? null, function (Builder $query, string $from): void {
                $query->whereDate('created_at', '>=', $from);
            })
            ->when($filters['to'] ?? null, function (Builder $query, string $to): void {
                $query->whereDate('created_at', '<=', $to);
            })
            ->latest()
            ->paginate((int) ($filters['per_page'] ?? 15));

        return ActivityLogResource::collection($activityLogs);
    }

    public function show(ActivityLog $activityLog): ActivityLogResource
    {
        $this->authorize('view', $activityLog);

        return new ActivityLogResource(
            $activityLog->load('user')
        );
    }
}
