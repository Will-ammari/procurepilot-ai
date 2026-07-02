<?php

namespace App\Services\Support;

use App\Models\Attachment;
use App\Models\Invoice;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AttachmentService
{
    private const SUBJECT_TYPE_MAP = [
        'purchase_request' => PurchaseRequest::class,
        'quote' => Quote::class,
        'invoice' => Invoice::class,
    ];

    public function paginatedForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Attachment::query()
            ->with(['uploadedBy'])
            ->where('organization_id', $user->organization_id)
            ->latest();

        if (! empty($filters['subject_type'])) {
            $query->where('attachable_type', self::SUBJECT_TYPE_MAP[$filters['subject_type']]);
        }

        if (! empty($filters['subject_id'])) {
            $query->where('attachable_id', $filters['subject_id']);
        }

        if (! empty($filters['mime_type'])) {
            $query->where('mime_type', $filters['mime_type']);
        }

        return $query->paginate(15);
    }

    public function storeForAttachable(Model $attachable, User $user, UploadedFile $file): Attachment
    {
        if (
            ! $attachable instanceof PurchaseRequest
            && ! $attachable instanceof Quote
            && ! $attachable instanceof Invoice
        ) {
            throw new InvalidArgumentException('Unsupported attachable model type.');
        }

        $organizationId = (int) $attachable->organization_id;
        $directory = $this->directoryFor($attachable);
        $path = $file->store($directory, Attachment::DISK_PUBLIC);

        return DB::transaction(function () use ($attachable, $user, $file, $organizationId, $path): Attachment {
            return Attachment::create([
                'organization_id' => $organizationId,
                'uploaded_by_user_id' => $user->id,
                'attachable_type' => $attachable::class,
                'attachable_id' => $attachable->getKey(),
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => basename($path),
                'disk' => Attachment::DISK_PUBLIC,
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize() ?: 0,
            ])->load(['uploadedBy']);
        });
    }

    public function delete(Attachment $attachment): void
    {
        $disk = $attachment->disk;
        $path = $attachment->path;

        DB::transaction(function () use ($attachment): void {
            $attachment->delete();
        });

        Storage::disk($disk)->delete($path);
    }

    private function directoryFor(Model $attachable): string
    {
        $type = Str::kebab(class_basename($attachable));

        return "attachments/{$type}/{$attachable->getKey()}";
    }
}
