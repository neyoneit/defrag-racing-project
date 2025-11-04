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
        Schema::create('alias_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // User being suggested the alias
            $table->foreignId('suggested_by_user_id')->constrained('users')->onDelete('cascade'); // User making the suggestion
            $table->string('alias');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('note')->nullable(); // Optional note from suggester
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('suggested_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alias_suggestions');
    }
};
