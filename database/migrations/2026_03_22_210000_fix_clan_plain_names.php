<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Recalculate plain_name for all clans using the corrected regex
        // Old regex only stripped ^0-^9, new one strips ^X where X is any character
        $clans = DB::table('clans')->get(['id', 'name']);

        foreach ($clans as $clan) {
            $plainName = preg_replace('/\^./', '', $clan->name);
            DB::table('clans')->where('id', $clan->id)->update(['plain_name' => $plainName]);
        }
    }

    public function down(): void
    {
        // Cannot reliably reverse this
    }
};
