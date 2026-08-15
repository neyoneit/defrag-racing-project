<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the file was last written on the uploader's own disk.
 *
 * An HTTP upload carries bytes and a filename and nothing else, so the date
 * the file has on the player's machine never reached us. It matters for comps:
 * a run has to have been made after the round's ballot opened, and the only
 * date inside a demo is a console line the mod prints offline - present on 89%
 * of offline demos and on 1 of 240 000 online ones.
 *
 * The client sends it, so it is not proof; it is one-directional evidence. A
 * file dated before the ballot opened is an old run. A file dated today proves
 * nothing, because copying, unzipping or downloading rewrites the date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->timestamp('client_file_mtime')->nullable()->after('record_date');
        });
    }

    public function down(): void
    {
        Schema::table('uploaded_demos', function (Blueprint $table) {
            $table->dropColumn('client_file_mtime');
        });
    }
};
