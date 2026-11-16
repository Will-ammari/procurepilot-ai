<?php

namespace Tests\Feature\Procurement;

use App\Models\ActivityLog;
use App\Models\ApprovalStep;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\QuoteComparison;
use App\Services\Procurement\ApprovalWorkflowService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\ProcurementTestScenario;
use Tests\TestCase;

class MySqlApprovalConcurrencyTest extends TestCase
{
    /**
     * This test deliberately uses committed records and independent PHP processes.
     * NEVER use RefreshDatabase or test transactions here: children could not see them.
     */
    public function test_simultaneous_approval_and_rejection_produce_one_decision(): void
    {
        // Opt-in is mandatory: this test uses migrate:fresh on the isolated schema.
        if (getenv('PROCUREPILOT_CONCURRENCY_TEST') !== '1') {
            $this->markTestSkipped('Dedicated concurrency test is opt-in only.');
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL/InnoDB required for real concurrency test.');
        }

        // Guard BEFORE any destructive migration: the name must be exact.
        if (app()->environment() !== 'testing' || DB::connection()->getDatabaseName() !== 'procurepilot_concurrency_test') {
            $this->fail('Refusing to migrate any database other than procurepilot_concurrency_test in APP_ENV=testing.');
        }

        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);

        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest([
            'status' => PurchaseRequest::STATUS_QUOTES_RECEIVED,
            'estimated_budget' => 900,
        ]);
        $quote = Quote::factory()->create([
            'organization_id' => $scenario->organization->id,
            'purchase_request_id' => $request->id,
            'vendor_id' => $scenario->vendor()->id,
        ]);
        QuoteComparison::create([
            'organization_id' => $scenario->organization->id,
            'purchase_request_id' => $request->id,
            'recommended_quote_id' => $quote->id,
            'generated_by_user_id' => $scenario->procurement->id,
            'currency' => 'EUR',
            'reason' => 'Recommended quote',
            'quotes' => [['quote_id' => $quote->id]],
            'weights' => ['total_amount' => 35],
        ]);

        $step = app(ApprovalWorkflowService::class)
            ->sendForApproval($request, $scenario->procurement)
            ->first();

        if (! $step instanceof ApprovalStep) {
            throw new \LogicException('Expected the approval workflow to return an approval step.');
        }

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
        foreach (['approve', 'reject'] as $action) {
            $readyFile = tempnam(sys_get_temp_dir(), 'approval-ready-');
            if ($readyFile === false) {
                throw new \RuntimeException('Could not create process coordination file.');
            }
            unlink($readyFile);
            $readyFiles[] = $readyFile;
            $processes[] = new Process([
                PHP_BINARY,
                base_path('tests/Concurrency/approval_worker.php'),
                (string) $step->id,
                (string) $scenario->manager->id,
                $action,
                $readyFile,
            ], base_path(), $environment, null, 20);
        }

        // Both workers must compete for the same parent row after the lock is freed.
        DB::beginTransaction();
        try {
            PurchaseRequest::query()->lockForUpdate()->findOrFail($request->id);
            foreach ($processes as $process) {
                $process->start();
            }
            $deadline = microtime(true) + 12;
            while (count(array_filter($readyFiles, 'file_exists')) !== count($readyFiles)) {
                if (microtime(true) >= $deadline) {
                    $this->fail('Child processes did not reach the decision barrier.');
                }
                usleep(20000);
            }
            // Give each worker time to issue its locking SELECT while the parent owns it.
            usleep(250000);
        } finally {
            DB::rollBack();
            foreach ($readyFiles as $readyFile) {
                if (file_exists($readyFile)) {
                    unlink($readyFile);
                }
            }
        }

        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }
        $this->assertEqualsCanonicalizing(['accepted', 'denied'], array_map(
            fn (Process $process): string => trim($process->getOutput()),
            $processes
        ));

        $this->assertSame(1, ApprovalStep::query()
            ->whereKey($step->id)
            ->whereIn('status', [ApprovalStep::STATUS_APPROVED, ApprovalStep::STATUS_REJECTED])
            ->count());
        $this->assertSame(1, ActivityLog::query()
            ->whereIn('event', [ActivityLog::EVENT_APPROVAL_APPROVED, ActivityLog::EVENT_APPROVAL_REJECTED])
            ->count());
    }
}
