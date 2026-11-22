<?php

namespace App\Console\Commands;

use App\Services\Monitoring\OperationalMetrics;
use App\Services\Monitoring\OperationsSnapshot;
use App\Services\Monitoring\PrometheusMetricsFormatter;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/** Snapshot-to-textfile exporter. Runs as a private scheduled Docker process. */
class ExportPrometheusMetrics extends Command
{
    protected $signature = 'ops:metrics:export
        {--output=/metrics/procurepilot.prom : Writable path in a private Docker volume}
        {--minutes=5 : Fixed lookback in minutes (1-60)}';

    protected $description = 'Atomically export bounded operational metrics for internal Prometheus scraping';

    public function handle(
        OperationalMetrics $metrics,
        OperationsSnapshot $snapshot,
        PrometheusMetricsFormatter $formatter
    ): int {
        $minutes = max(1, min(60, (int) $this->option('minutes')));
        $path = (string) $this->option('output');
        $directory = dirname($path);
        $tempPath = null;

        try {
            if (! is_dir($directory) || ! is_writable($directory)) {
                throw new RuntimeException('Metrics output directory is missing or not writable.');
            }

            $content = $formatter->format($metrics->snapshot($minutes), $snapshot->collect(), $minutes, time());
            $tempPath = tempnam($directory, '.procurepilot-');
            if ($tempPath === false || file_put_contents($tempPath, $content, LOCK_EX) !== strlen($content)) {
                throw new RuntimeException('Could not write metrics snapshot.');
            }
            chmod($tempPath, 0644);
            if (! rename($tempPath, $path)) {
                throw new RuntimeException('Could not replace metrics snapshot atomically.');
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Fail visibly. Do not publish invented zero measurements on DB outage.
            $this->error('Metrics export failed: '.$exception::class);

            return self::FAILURE;
        } finally {
            if (is_string($tempPath) && is_file($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
