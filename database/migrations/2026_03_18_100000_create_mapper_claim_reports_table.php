<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mapper_claim_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mapper_claim_id')->constrained('mapper_claims')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending, resolved, dismissed
            $table->text('admin_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['reporter_id', 'mapper_claim_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mapper_claim_reports');
    }
};
