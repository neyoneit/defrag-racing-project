<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asking for a wish to be taken down, instead of taking it down.
 *
 * Once a wish is on the list it is not only its author's any more: other people
 * voted on it, and some of them voted because of it rather than for it. An
 * author who can delete at will can withdraw an idea the moment it collects
 * downvotes, which quietly edits the record everyone else was reading.
 *
 * So the author asks and the admin decides. The request is a timestamp on the
 * wish rather than a table of its own - there is only ever one open request per
 * wish, and clearing it is the whole of "no".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishes', function (Blueprint $table) {
            $table->timestamp('removal_requested_at')->nullable()->index()->after('approved_by');
            $table->string('removal_reason')->nullable()->after('removal_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('wishes', function (Blueprint $table) {
            $table->dropColumn(['removal_requested_at', 'removal_reason']);
        });
    }
};
