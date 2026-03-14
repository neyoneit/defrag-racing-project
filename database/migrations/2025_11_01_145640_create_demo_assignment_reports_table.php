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
        Schema::create('demo_assignment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demo_id')->constrained('uploaded_demos')->onDelete('cascade');
            $table->enum('report_type', ['reassignment_request', 'wrong_assignment', 'bad_demo']);
            $table->foreignId('reported_by_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('current_record_id')->nullable()->constrained('records')->onDelete('set null');
            $table->foreignId('suggested_record_id')->nullable()->constrained('records')->onDelete('set null');
            $table->string('reason_type'); // Predefined reason selected
            $table->text('reason_details')->nullable(); // Optional additional details
            $table->enum('status', ['pending', 'approved', 'rejected', 'resolved'])->default('pending');
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'report_type']);
            $table->index('demo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_assignment_reports');
    }
};
