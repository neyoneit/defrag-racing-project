<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('map_news')->default(true)->after('tournament_news');
        });

        // Anyone who ever saved their notification settings has a preview list
        // that predates this type, so the new notification would land in the
        // notification center and never in the header preview. Add it for them.
        // A null column falls back to the default list in HandleInertiaRequests
        // and already includes it.
        DB::table('users')
            ->whereNotNull('preview_system')
            ->orderBy('id')
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    $preview = json_decode($user->preview_system, true);

                    if (! is_array($preview) || in_array('map', $preview, true)) {
                        continue;
                    }

                    $preview[] = 'map';

                    DB::table('users')->where('id', $user->id)->update([
                        'preview_system' => json_encode($preview),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('map_news');
        });
    }
};
