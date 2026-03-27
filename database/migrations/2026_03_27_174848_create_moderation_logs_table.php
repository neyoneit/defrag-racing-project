<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('area');         // e.g. 'mapper_claims', 'record_flags', 'models', 'alias_reports', 'demo_assignments'
            $table->string('action');       // e.g. 'resolved', 'dismissed', 'approved', 'rejected', 'deleted', 'restored'
            $table->nullableMorphs('subject'); // the record being acted on
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('area');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moderation_logs');
    }
};
