<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Copy legacy public documents into private storage, update references,
 * then remove the exposed public objects. Rerunnable after partial failures.
 */
class SecureLegacyAttachments extends Command
{
    protected $signature = 'attachments:secure-public {--dry-run : Count exposed records without changing files}';

    protected $description = 'Move existing public supplier documents to private local storage';

    public function handle(): int
    {
        $count = Attachment::query()->where('disk', Attachment::DISK_PUBLIC)->count();
        if ($this->option('dry-run')) {
            $this->info("{$count} public attachments require migration.");

            return self::SUCCESS;
        }

        $moved = 0;
        $failed = 0;

        Attachment::query()->where('disk', Attachment::DISK_PUBLIC)
            ->orderBy('id')->chunkById(100, function ($attachments) use (&$moved, &$failed): void {
                foreach ($attachments as $attachment) {
                    try {
                        $source = Storage::disk(Attachment::DISK_PUBLIC);
                        $private = Storage::disk(Attachment::DISK_PRIVATE);
                        $target = sprintf(
                            'organizations/%d/attachments/legacy/%d/%s',
                            $attachment->organization_id,
                            $attachment->id,
                            basename($attachment->path)
                        );

                        $stream = $source->readStream($attachment->path);
                        if (! is_resource($stream)) {
                            throw new \RuntimeException('Source document is missing or unreadable.');
                        }

                        try {
                            if (! $private->put($target, $stream)) {
                                throw new \RuntimeException('Private document copy failed.');
                            }
                        } finally {
                            fclose($stream);
                        }

                        if ($private->size($target) !== $source->size($attachment->path)) {
                            throw new \RuntimeException('Private document copy failed size verification.');
                        }

                        $updated = Attachment::query()
                            ->whereKey($attachment->id)
                            ->where('disk', Attachment::DISK_PUBLIC)
                            ->update(['disk' => Attachment::DISK_PRIVATE, 'path' => $target, 'stored_name' => basename($target)]);

                        if ($updated !== 1) {
                            // A concurrent migration won; do not delete the shared source.
                            continue;
                        }

                        $stillReferenced = Attachment::query()
                            ->where('disk', Attachment::DISK_PUBLIC)
                            ->where('path', $attachment->path)
                            ->exists();

                        if (! $stillReferenced && ! $source->delete($attachment->path)) {
                            throw new \RuntimeException('Reference secured; public original could not be removed.');
                        }

                        $moved++;
                    } catch (Throwable $exception) {
                        $failed++;
                        $this->error("Attachment {$attachment->id}: {$exception->getMessage()}");
                    }
                }
            });

        $this->info("Secured: {$moved}; errors: {$failed}; remaining public DB records: ".Attachment::query()->where('disk', Attachment::DISK_PUBLIC)->count());

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
