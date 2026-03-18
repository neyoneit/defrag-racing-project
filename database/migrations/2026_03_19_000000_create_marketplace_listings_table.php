<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('listing_type'); // "request" or "offer"
            $table->string('work_type'); // "map", "player_model", "weapon_model", "shadow_model"
            $table->string('title', 255);
            $table->text('description');
            $table->string('budget')->nullable();
            $table->string('status')->default('open'); // open, in_progress, completed, cancelled
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index('user_id');
            $table->index('listing_type');
            $table->index('status');
            $table->index('assigned_to_user_id');
            $table->index(['listing_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listings');
    }
};
