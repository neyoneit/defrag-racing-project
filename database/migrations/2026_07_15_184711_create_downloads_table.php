<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('downloads', function (Blueprint $table) {
            $table->id();

            // Null for entries created by a sync command rather than a person.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // restrict, not cascade: cascading a category delete would drop
            // user uploads at the DB level, past every purge hook, leaving
            // their objects orphaned in the bucket forever.
            $table->foreignId('category_id')->constrained('download_categories')->restrictOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('youtube_url')->nullable();

            // Set when the entry only points elsewhere (q3defrag.org, a repo)
            // instead of owning files in download_files.
            $table->string('external_url')->nullable();

            // Identifies an auto-synced entry so a re-sync updates instead of
            // duplicating it, e.g. 'defrag_mod:1.91'.
            $table->string('source_key')->nullable()->unique();

            $table->boolean('is_locked')->default(false);
            $table->string('status')->default('published'); // published | hidden | rejected

            // Marks assets that only make sense under the DeFRaG mod (HUDs,
            // configs) so the listing can filter them out for baseq3 users.
            $table->boolean('defrag_only')->default(false);

            $table->unsignedBigInteger('downloads_count')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('user_id');
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('downloads');
    }
};
