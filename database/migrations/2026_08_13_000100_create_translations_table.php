<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anything an admin writes in Filament and a visitor then reads - the terms,
 * the privacy policy, an announcement - is a row in a table, not a string in
 * a template, so `lang:sync` can never see it and `lang/*.json` can never
 * hold it. It needs its translation stored next to it.
 *
 * One table for every model rather than a column per language on each: a new
 * language is then a row, not a migration, and a model becomes translatable
 * by listing its fields rather than by changing its schema. Nothing existing
 * moves, and the English text stays exactly where it is - which is also the
 * fallback, so a missing translation shows English the same way a blank line
 * in a language file does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->morphs('translatable');
            $table->string('locale', 8);
            $table->string('field', 64);
            $table->longText('value')->nullable();
            $table->timestamps();

            // One value per field per language. The lookup always knows all
            // four, so this is the index the reads use as well.
            $table->unique(['translatable_type', 'translatable_id', 'locale', 'field'], 'translations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
