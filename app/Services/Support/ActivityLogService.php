<?php

namespace App\Services\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use InvalidArgumentException;

class ActivityLogService
{
    public function log(
        string $event,
        ?User $user = null,
        ?Model $subject = null,
        array $metadata = [],
        ?int $organizationId = null
    ): ActivityLog {
        $resolvedOrganizationId = $organizationId
            ?? $this->organizationIdFromUser($user)
            ?? $this->organizationIdFromSubject($subject);

        if ($resolvedOrganizationId === null) {
            throw new InvalidArgumentException('Activity log requires an organization context.');
        }

        return ActivityLog::create([
            'organization_id' => $resolvedOrganizationId,
            'user_id' => $user?->id,
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    private function organizationIdFromUser(?User $user): ?int
    {
        return $user?->organization_id;
    }

    private function organizationIdFromSubject(?Model $subject): ?int
    {
        if ($subject === null) {
            return null;
        }

        $organizationId = $subject->getAttribute('organization_id');

        return $organizationId !== null ? (int) $organizationId : null;
    }
}
