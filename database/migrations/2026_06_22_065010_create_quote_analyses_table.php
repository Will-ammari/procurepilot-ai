<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_analyses', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('quote_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->longText('raw_text')->nullable();
            $table->text('summary');
            $table->json('extracted_terms');
            $table->json('hidden_costs');
            $table->json('risk_notes');
            $table->decimal('confidence_score', 5, 2)->default(0.00);
            $table->string('model_name')->nullable();
            $table->string('status')->default('completed');

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_analyses');
    }
};
