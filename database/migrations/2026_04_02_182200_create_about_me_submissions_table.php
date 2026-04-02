<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('about_me_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->comment('Profile owner');
            $table->foreignId('submitted_by')->constrained('users')->comment('Who wrote it');
            $table->text('content')->nullable()->comment('New about me text, null for delete requests');
            $table->string('type')->default('create')->comment('create, edit, delete');
            $table->string('status')->default('pending')->comment('pending, approved, rejected');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_me_submissions');
    }
};
