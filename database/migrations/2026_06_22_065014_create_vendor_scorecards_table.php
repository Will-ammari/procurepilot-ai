<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_scorecards', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('vendor_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('total_quotes')->default(0);
            $table->unsignedInteger('accepted_quotes')->default(0);
            $table->decimal('win_rate', 5, 2)->default(0);

            $table->decimal('average_delivery_days', 8, 2)->nullable();

            $table->unsignedInteger('total_invoices')->default(0);
            $table->unsignedInteger('paid_invoices')->default(0);
            $table->unsignedInteger('invoice_issue_count')->default(0);

            $table->decimal('total_invoiced_amount', 14, 2)->default(0);
            $table->string('currency', 3)->default('EUR');

            $table->decimal('overall_score', 5, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'vendor_id']);
            $table->index(['organization_id', 'overall_score']);
            $table->index(['vendor_id', 'overall_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_scorecards');
    }
};
