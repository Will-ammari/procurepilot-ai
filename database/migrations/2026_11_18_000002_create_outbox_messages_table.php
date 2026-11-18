<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('event_key', 160)->unique();
            $table->string('event_type', 90);
            $table->json('payload');
            $table->uuid('correlation_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('reserved_until')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->string('last_error_code', 160)->nullable();
            $table->timestamps();

            $table->index(['status', 'next_attempt_at', 'id'], 'outbox_ready_idx');
            $table->index(['status', 'reserved_until', 'id'], 'outbox_lease_idx');
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
