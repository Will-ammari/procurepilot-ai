<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('purchase_request_id')
                ->constrained('purchase_requests')
                ->cascadeOnDelete();

            $table->foreignId('vendor_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('delivery_days')->nullable();
            $table->string('payment_terms')->nullable();
            $table->unsignedInteger('warranty_months')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');

            $table->timestamps();

            $table->unique(['purchase_request_id', 'vendor_id']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
