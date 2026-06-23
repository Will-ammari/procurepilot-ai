<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->date('needed_by_date')->nullable();
            $table->decimal('estimated_budget', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('priority')->default('normal');
            $table->string('status')->default('draft');

            $table->unsignedBigInteger('approved_quote_id')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index(['requester_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
