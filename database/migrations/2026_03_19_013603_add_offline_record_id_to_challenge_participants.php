<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenge_participants', function (Blueprint $table) {
            $table->foreignId('offline_record_id')->nullable()->after('record_id')->constrained('offline_records')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('challenge_participants', function (Blueprint $table) {
            $table->dropForeign(['offline_record_id']);
            $table->dropColumn('offline_record_id');
        });
    }
};
