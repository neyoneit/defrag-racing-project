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
        Schema::create('record_flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('record_id')->nullable()->index();
            $table->unsignedBigInteger('demo_id')->nullable()->index();
            $table->string('flag_type'); // sv_cheats, tool_assisted, client_finish, timescale, g_speed, g_gravity, etc.
            $table->unsignedBigInteger('flagged_by_user_id');
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('note')->nullable();
            $table->unsignedBigInteger('resolved_by_admin_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->foreign('record_id')->references('id')->on('records')->cascadeOnDelete();
            $table->foreign('demo_id')->references('id')->on('uploaded_demos')->cascadeOnDelete();
            $table->foreign('flagged_by_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('resolved_by_admin_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['status', 'flag_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_flags');
    }
};
