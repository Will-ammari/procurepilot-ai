<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('quote_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('purchase_request_item_id')
                ->nullable()
                ->constrained('purchase_request_items')
                ->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('total_price', 12, 2)->nullable();

            $table->timestamps();

            $table->index('quote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_items');
    }
};
