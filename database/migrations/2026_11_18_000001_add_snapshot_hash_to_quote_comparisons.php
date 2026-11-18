<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_comparisons', function (Blueprint $table): void {
            // Legacy comparisons have no verifiable snapshot and must be regenerated.
            $table->string('snapshot_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quote_comparisons', function (Blueprint $table): void {
            $table->dropColumn('snapshot_hash');
        });
    }
};
