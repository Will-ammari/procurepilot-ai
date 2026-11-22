<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Deployment configuration preflight, not a penetration test.
 * Only reports setting names, never credential or environment values.
 */
class SecurityConfigurationAudit extends Command
{
    protected $signature = 'ops:security:audit {--strict : Return non-zero if unsafe for production}';

    protected $description = 'Report safe deployment configuration requirements (no secret values)';

    public function handle(): int
    {
        $key = (string) config('app.key');
        $rawKey = str_starts_with($key, 'base64:')
            ? base64_decode(substr($key, 7), true)
            : $key;
        $expectedKeyLength = config('app.cipher') === 'AES-128-CBC' ? 16 : 32;

        $checks = [
            'debug_disabled' => ! (bool) config('app.debug'),
            'encryption_key_present' => is_string($rawKey) && strlen($rawKey) === $expectedKeyLength,
            'https_app_url' => str_starts_with((string) config('app.url'), 'https://'),
            'cors_allowlist' => ! in_array('*', (array) config('cors.allowed_origins'), true)
                && (array) config('cors.allowed_origins_patterns') === [],
            'asynchronous_queue' => ! in_array(config('queue.default'), ['sync', 'null'], true),
            'non_ephemeral_cache' => ! in_array(config('cache.default'), ['array', 'null'], true),
        ];
        foreach ($checks as $name => $passed) {
            $this->line(($passed ? 'PASS' : 'REVIEW').' '.$name);
        }

        $this->line('Configuration preflight only; verify TLS/proxy, secrets, IAM, backup/restore and security testing separately.');
        if ($this->option('strict') && in_array(false, $checks, true)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
