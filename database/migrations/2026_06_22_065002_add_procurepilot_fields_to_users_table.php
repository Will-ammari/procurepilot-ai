<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('department_id')
                ->nullable()
                ->after('organization_id')
                ->constrained()
                ->nullOnDelete();

            $table->string('role')
                ->default('requester')
                ->after('password');

            $table->index(['organization_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'role']);
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('role');
        });
    }
};
