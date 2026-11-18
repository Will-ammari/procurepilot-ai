<?php

namespace Tests\Feature\Operations;

use App\Models\ActivityLog;
use App\Models\OutboxMessage;
use App\Services\Procurement\PurchaseRequestService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\ProcurementTestScenario;
use Tests\TestCase;

class MySqlOutboxConcurrencyTest extends TestCase
{
    /** Uses two independent PHP processes, not an SQLite in-memory test double. */
    public function test_competing_publishers_claim_one_event_once(): void
    {
        if (getenv('PROCUREPILOT_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Dedicated MySQL concurrency tests are opt-in only.');
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL is required for this concurrency test.');
        }

        if (app()->environment() !== 'testing'
            || DB::connection()->getDatabaseName() !== 'procurepilot_concurrency_test') {
            $this->fail('Refusing to reset any database other than procurepilot_concurrency_test.');
        }

        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest();
        app(PurchaseRequestService::class)->submit($request, $scenario->requester);
        $message = OutboxMessage::query()->firstOrFail();

        $environment = [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) config('database.connections.mysql.host'),
            'DB_PORT' => (string) config('database.connections.mysql.port'),
            'DB_DATABASE' => 'procurepilot_concurrency_test',
            'DB_USERNAME' => (string) config('database.connections.mysql.username'),
            'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ];
        $processes = [];
        $readyFiles = [];

        foreach (range(1, 2) as $worker) {
            $ready = tempnam(sys_get_temp_dir(), 'outbox-ready-');
            if ($ready === false) {
                throw new RuntimeException('Could not create coordination file.');
            }
            unlink($ready);
            $readyFiles[] = $ready;
            $processes[] = new Process([
                PHP_BINARY,
                base_path('tests/Concurrency/outbox_worker.php'),
                $ready,
            ], base_path(), $environment, null, 20);
        }

        // Hold the event row while both publishers begin their claim SELECT.
        DB::beginTransaction();
        try {
            OutboxMessage::query()->lockForUpdate()->findOrFail($message->id);
            foreach ($processes as $process) {
                $process->start();
            }
            $deadline = microtime(true) + 12;
            while (count(array_filter($readyFiles, 'file_exists')) !== count($readyFiles)) {
                if (microtime(true) >= $deadline) {
                    $this->fail('Child publishers did not reach the claim barrier.');
                }
                usleep(20000);
            }
            usleep(250000);
        } finally {
            DB::rollBack();
            foreach ($readyFiles as $readyFile) {
                if (file_exists($readyFile)) {
                    unlink($readyFile);
                }
            }
        }

        $totalDispatched = 0;
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $output = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
            $totalDispatched += (int) $output['dispatched'];
        }

        $this->assertSame(1, $totalDispatched);
        $this->assertSame(OutboxMessage::STATUS_DISPATCHED, $message->fresh()->status);
        $this->assertSame(1, ActivityLog::query()
            ->where('idempotency_key', 'purchase_request.submitted:'.$request->id)
            ->count());
    }
}
