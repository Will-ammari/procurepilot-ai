<?php

namespace Tests\Feature\Operations;

use App\Jobs\Procurement\RecordPurchaseRequestSubmitted;
use App\Models\ActivityLog;
use App\Models\OutboxMessage;
use App\Models\PurchaseRequest;
use App\Services\Outbox\OutboxPublisher;
use App\Services\Outbox\OutboxTransport;
use App\Services\Procurement\PurchaseRequestService;
use App\Services\Support\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Support\ProcurementTestScenario;
use Tests\TestCase;

class OutboxReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_is_atomic_with_outbox_and_queue_publication_is_delayed(): void
    {
        Queue::fake();
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest();

        app(PurchaseRequestService::class)->submit($request, $scenario->requester);

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $request->id,
            'status' => PurchaseRequest::STATUS_SUBMITTED,
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'organization_id' => $scenario->organization->id,
            'event_key' => 'purchase_request.submitted:'.$request->id,
            'status' => OutboxMessage::STATUS_PENDING,
        ]);
        Queue::assertNotPushed(RecordPurchaseRequestSubmitted::class);

        $this->assertSame(1, app(OutboxPublisher::class)->publish()['dispatched']);
        Queue::assertPushedOn('procurement-events', RecordPurchaseRequestSubmitted::class);
        $this->assertSame(0, app(OutboxPublisher::class)->publish()['claimed']);
        Queue::assertPushed(RecordPurchaseRequestSubmitted::class, 1);
    }

    public function test_transaction_rollback_also_rolls_back_outbox_write(): void
    {
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest();

        $caught = null;
        try {
            DB::transaction(function () use ($scenario, $request): void {
                app(PurchaseRequestService::class)->submit($request, $scenario->requester);
                throw new RuntimeException('Simulated failure after outbox insert.');
            });
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        $this->assertInstanceOf(RuntimeException::class, $caught);
        $this->assertSame('Simulated failure after outbox insert.', $caught->getMessage());

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $request->id,
            'status' => PurchaseRequest::STATUS_DRAFT,
        ]);
        $this->assertDatabaseCount('outbox_messages', 0);
    }

    public function test_transport_failure_is_retryable_without_losing_the_event(): void
    {
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest();
        app(PurchaseRequestService::class)->submit($request, $scenario->requester);
        $transport = new class implements OutboxTransport
        {
            public bool $fail = true;

            public int $deliveries = 0;

            public function deliver(OutboxMessage $message): void
            {
                if ($this->fail) {
                    throw new RuntimeException('Simulated Redis outage.');
                }

                $this->deliveries++;
            }
        };
        $this->app->instance(OutboxTransport::class, $transport);
        $publisher = app(OutboxPublisher::class);

        $this->assertSame(1, $publisher->publish()['retried']);
        $this->assertDatabaseHas('outbox_messages', [
            'event_key' => 'purchase_request.submitted:'.$request->id,
            'status' => OutboxMessage::STATUS_PENDING,
            'attempts' => 1,
        ]);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertSame(0, $publisher->publish()['claimed']);

        $transport->fail = false;
        $this->travel(16)->seconds();
        $this->assertSame(1, $publisher->publish()['dispatched']);
        $this->assertSame(1, $transport->deliveries);
        $this->assertDatabaseHas('outbox_messages', [
            'event_key' => 'purchase_request.submitted:'.$request->id,
            'status' => OutboxMessage::STATUS_DISPATCHED,
        ]);
    }

    public function test_expired_lease_is_recovered_and_event_is_not_lost(): void
    {
        Queue::fake();
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest();
        app(PurchaseRequestService::class)->submit($request, $scenario->requester);
        OutboxMessage::query()->update([
            'status' => OutboxMessage::STATUS_PROCESSING,
            'reserved_until' => now()->subSecond(),
            'lease_token' => 'a6e5f2bc-d8a3-44e0-98a6-263dc8484099',
            'attempts' => 1,
        ]);

        $this->assertSame(1, app(OutboxPublisher::class)->publish()['dispatched']);
        $this->assertDatabaseHas('outbox_messages', [
            'event_key' => 'purchase_request.submitted:'.$request->id,
            'attempts' => 2,
            'status' => OutboxMessage::STATUS_DISPATCHED,
        ]);
        Queue::assertPushed(RecordPurchaseRequestSubmitted::class, 1);
    }

    public function test_exhausted_event_needs_explicit_manual_retry(): void
    {
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest();
        app(PurchaseRequestService::class)->submit($request, $scenario->requester);
        OutboxMessage::query()->update(['attempts' => 11]);
        $this->app->instance(OutboxTransport::class, new class implements OutboxTransport
        {
            public function deliver(OutboxMessage $message): void
            {
                throw new RuntimeException('Simulated persistent queue outage.');
            }
        });

        $this->assertSame(1, app(OutboxPublisher::class)->publish()['failed']);
        $message = OutboxMessage::query()->firstOrFail();
        $this->assertSame(OutboxMessage::STATUS_FAILED, $message->fresh()->status);
        $this->assertSame(0, app(OutboxPublisher::class)->publish()['claimed']);
        $this->artisan('ops:outbox:retry', ['id' => $message->id])->assertExitCode(0);
        $this->assertSame(OutboxMessage::STATUS_PENDING, $message->fresh()->status);
        $this->assertSame(0, $message->fresh()->attempts);
    }

    public function test_retention_is_dry_run_first_and_never_prunes_undelivered_events(): void
    {
        $scenario = new ProcurementTestScenario;
        $first = $scenario->purchaseRequest();
        $second = $scenario->purchaseRequest();
        $workflow = app(PurchaseRequestService::class);
        $workflow->submit($first, $scenario->requester);
        $workflow->submit($second, $scenario->requester);

        OutboxMessage::query()->where('event_key', 'purchase_request.submitted:'.$first->id)
            ->update([
                'status' => OutboxMessage::STATUS_DISPATCHED,
                'dispatched_at' => now()->subDays(40),
            ]);

        $this->artisan('ops:outbox:prune', ['--days' => 30])->assertExitCode(0);
        $this->assertDatabaseCount('outbox_messages', 2);
        $this->artisan('ops:outbox:prune', ['--days' => 30, '--force' => true])
            ->assertExitCode(0);
        $this->assertDatabaseCount('outbox_messages', 1);
        $this->assertDatabaseHas('outbox_messages', [
            'event_key' => 'purchase_request.submitted:'.$second->id,
            'status' => OutboxMessage::STATUS_PENDING,
        ]);
    }

    public function test_unrecognized_event_type_fails_closed_without_executing_jobs(): void
    {
        Queue::fake();
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest();
        app(PurchaseRequestService::class)->submit($request, $scenario->requester);
        OutboxMessage::query()->update(['event_type' => 'user_supplied.job.class']);

        $this->assertSame(1, app(OutboxPublisher::class)->publish()['retried']);
        Queue::assertNotPushed(RecordPurchaseRequestSubmitted::class);
        $this->assertDatabaseHas('outbox_messages', [
            'event_key' => 'purchase_request.submitted:'.$request->id,
            'status' => OutboxMessage::STATUS_PENDING,
        ]);
    }

    public function test_invalid_event_payload_is_not_published(): void
    {
        Queue::fake();
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest();
        app(PurchaseRequestService::class)->submit($request, $scenario->requester);

        OutboxMessage::query()->update(['payload' => json_encode([
            'purchase_request_id' => 'not-an-integer',
            'submitted_by_user_id' => $scenario->requester->id,
            'submitted_at' => now()->toISOString(),
        ], JSON_THROW_ON_ERROR)]);

        $this->assertSame(1, app(OutboxPublisher::class)->publish()['retried']);
        Queue::assertNotPushed(RecordPurchaseRequestSubmitted::class);
        $this->assertDatabaseHas('outbox_messages', [
            'event_key' => 'purchase_request.submitted:'.$request->id,
            'status' => OutboxMessage::STATUS_PENDING,
        ]);
    }

    public function test_consumer_handles_duplicate_delivery_without_duplicate_business_audit(): void
    {
        $scenario = new ProcurementTestScenario;
        $request = $scenario->purchaseRequest(['status' => PurchaseRequest::STATUS_SUBMITTED]);
        $job = new RecordPurchaseRequestSubmitted($request->id, $scenario->requester->id);

        $job->handle(app(ActivityLogService::class));
        $job->handle(app(ActivityLogService::class));

        $this->assertSame(1, ActivityLog::query()
            ->where('idempotency_key', 'purchase_request.submitted:'.$request->id)
            ->count());
    }
}
