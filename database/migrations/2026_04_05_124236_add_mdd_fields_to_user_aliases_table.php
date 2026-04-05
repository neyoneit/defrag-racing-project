<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_aliases', function (Blueprint $table) {
            // Make user_id nullable (MDD-only players don't have user accounts)
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // MDD profile link (primary identifier for aliases)
            $table->unsignedInteger('mdd_id')->nullable()->after('user_id');

            // Colored alias (Quake 3 color codes)
            $table->string('alias_colored')->nullable()->after('alias');

            // How many records used this alias
            $table->unsignedInteger('usage_count')->default(0)->after('alias_colored');

            // Source: manual (user-added) or mdd_import (from q3df.org scrape)
            $table->string('source', 20)->default('manual')->after('usage_count');

            // Drop global unique, add index for lookups (no unique - duplicates handled in code)
            $table->dropUnique(['alias']);
            $table->index(['mdd_id', 'alias_colored'], 'user_aliases_mdd_alias_colored_idx');

            $table->index('mdd_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_aliases', function (Blueprint $table) {
            $table->dropIndex('user_aliases_mdd_alias_colored_idx');
            $table->dropIndex(['mdd_id']);

            $table->dropColumn(['mdd_id', 'alias_colored', 'usage_count', 'source']);

            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unique('alias');
        });
    }
};
