<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Work types used to be four strings hardcoded in about twenty places.
     * They now live here, so the community can suggest one and an admin can
     * approve it without a deploy.
     *
     * The four originals are seeded as core rows and carry the translations
     * they already had in lang/*.json, so nothing on the site changes wording
     * the day this runs.
     */
    public function up(): void
    {
        Schema::create('marketplace_work_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();

            // English is the fallback for every language. label is what a
            // single listing is tagged with, label_plural is what a creator
            // lists as a specialty ("Player Models").
            $table->string('label');
            $table->string('label_plural')->nullable();
            $table->string('description')->nullable();

            // {"cs": {"label": "Mapa", "label_plural": "...", "description": "..."}}
            $table->json('translations')->nullable();

            // A palette key, not a CSS class: Tailwind only keeps classes it
            // can see as literals, so the classes live in one JS map.
            $table->string('color')->default('gray');

            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->boolean('is_core')->default(false);   // cannot be deleted
            $table->unsignedInteger('sort_order')->default(100);

            $table->unsignedBigInteger('suggested_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });

        $seed = [
            ['map', 'Map', 'Mapping', 'Custom map creation', 'emerald', 10],
            ['player_model', 'Player Model', 'Player Models', 'Custom player skin/model', 'purple', 20],
            ['weapon_model', 'Weapon Model', 'Weapon Models', 'Custom weapon skin/model', 'orange', 30],
            ['shadow_model', 'Shadow Model', 'Shadow Models', 'Custom shadow model', 'cyan', 40],
        ];

        // Lift the wording these four already have out of the language files,
        // so a Czech visitor sees exactly what they saw yesterday.
        $locales = [];
        foreach (glob(lang_path('*.json')) as $file) {
            $locales[basename($file, '.json')] = json_decode(file_get_contents($file), true) ?: [];
        }

        $now = now();

        foreach ($seed as [$slug, $label, $plural, $description, $color, $sort]) {
            $translations = [];

            foreach ($locales as $locale => $strings) {
                $row = array_filter([
                    'label' => $strings[$label] ?? null,
                    'label_plural' => $strings[$plural] ?? null,
                    'description' => $strings[$description] ?? null,
                ]);

                if ($row) {
                    $translations[$locale] = $row;
                }
            }

            DB::table('marketplace_work_types')->insert([
                'slug' => $slug,
                'label' => $label,
                'label_plural' => $plural,
                'description' => $description,
                'translations' => $translations ? json_encode($translations) : null,
                'color' => $color,
                'status' => 'approved',
                'is_core' => true,
                'sort_order' => $sort,
                'approved_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_work_types');
    }
};
