<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50); // js_error, api_error, vue_error
            $table->string('message', 1000);
            $table->text('stack')->nullable();
            $table->string('url', 500); // page URL where error occurred
            $table->string('endpoint', 500)->nullable(); // API endpoint (for api_error)
            $table->smallInteger('status_code')->nullable(); // HTTP status code
            $table->text('request_data')->nullable(); // request payload (JSON)
            $table->text('response_data')->nullable(); // response body (JSON)
            $table->string('component', 200)->nullable(); // Vue component name
            $table->string('user_agent', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_errors');
    }
};
