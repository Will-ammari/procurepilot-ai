<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_comparisons', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('purchase_request_id')
                ->constrained('purchase_requests')
                ->cascadeOnDelete();

            $table->foreignId('recommended_quote_id')
                ->nullable()
                ->constrained('quotes')
                ->nullOnDelete();

            $table->foreignId('generated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('currency', 3)->default('EUR');
            $table->text('reason')->nullable();
            $table->json('quotes');
            $table->json('weights');

            $table->timestamps();

            $table->index(['organization_id', 'purchase_request_id']);
            $table->index('recommended_quote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_comparisons');
    }
};
