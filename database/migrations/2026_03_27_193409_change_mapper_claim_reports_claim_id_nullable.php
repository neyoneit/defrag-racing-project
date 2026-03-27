<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mapper_claim_reports', function (Blueprint $table) {
            $table->dropForeign(['mapper_claim_id']);
            $table->unsignedBigInteger('mapper_claim_id')->nullable()->change();
            $table->foreign('mapper_claim_id')->references('id')->on('mapper_claims')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mapper_claim_reports', function (Blueprint $table) {
            $table->dropForeign(['mapper_claim_id']);
            $table->unsignedBigInteger('mapper_claim_id')->nullable(false)->change();
            $table->foreign('mapper_claim_id')->references('id')->on('mapper_claims')->cascadeOnDelete();
        });
    }
};
