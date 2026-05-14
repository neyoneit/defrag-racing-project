<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundle_categories', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('name');
        });

        Schema::table('bundles', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('category_id');
        });

        // Backfill existing rows so they keep current visual order (id ASC)
        $cats = DB::table('bundle_categories')->orderBy('id')->pluck('id');
        foreach ($cats as $i => $id) {
            DB::table('bundle_categories')->where('id', $id)->update(['position' => $i]);
        }

        $catIds = DB::table('bundles')->select('category_id')->distinct()->pluck('category_id');
        foreach ($catIds as $catId) {
            $bundles = DB::table('bundles')->where('category_id', $catId)->orderBy('id')->pluck('id');
            foreach ($bundles as $i => $id) {
                DB::table('bundles')->where('id', $id)->update(['position' => $i]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bundle_categories', function (Blueprint $table) {
            $table->dropColumn('position');
        });

        Schema::table('bundles', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
