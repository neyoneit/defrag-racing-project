<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fastcaps servers write their demos into `ctf_<physics>_<n>` rather than
 * `cpm` / `vq3`, so the directory carries the mode as well as the physics -
 * `run` or `ctf1`..`ctf7`, the same values `records.mode` uses.
 *
 * The first index run left 1837 fastcaps demos with no physics at all, since
 * the parser only recognised the two plain directories. Without the mode a
 * fastcaps record could never be matched to its demo: the same player can
 * hold a time on the same map in several ctf modes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_demos', function (Blueprint $table) {
            $table->string('mode', 10)->nullable()->after('physics');
        });
    }

    public function down(): void
    {
        Schema::table('server_demos', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
