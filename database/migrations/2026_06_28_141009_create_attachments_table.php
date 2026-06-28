<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();

            $table->foreignIdFor(Organization::class)
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignIdFor(User::class, 'uploaded_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->morphs('attachable');

            $table->string('original_name');
            $table->string('stored_name');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);

            $table->timestamps();

            $table->index(['organization_id', 'uploaded_by_user_id']);
            $table->index(['organization_id', 'created_at']);
            $table->index('mime_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
