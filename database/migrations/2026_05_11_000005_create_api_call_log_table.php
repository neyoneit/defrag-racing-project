<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_call_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Null when the user authenticated via session cookie (browser).
            // Populated with the Sanctum token's ID when they used a Bearer token.
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('route', 191)->comment('Request URI path, e.g. /api/records/search');
            $table->string('method', 8)->comment('HTTP verb');
            $table->ipAddress('ip')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->unsignedInteger('response_ms')->nullable()->comment('Server processing time in ms');
            $table->timestamp('created_at')->useCurrent();

            // Filament filter "show me this user's last week of calls"
            $table->index(['user_id', 'created_at']);
            // Filament filter "show me everyone's calls today"
            $table->index('created_at');
            // Filament filter "who hit /api/records/search?"
            $table->index(['route', 'created_at']);
            // Per-token usage stats
            $table->index(['token_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_call_log');
    }
};
