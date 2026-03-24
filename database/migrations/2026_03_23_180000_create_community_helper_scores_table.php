<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_helper_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Content contributions
            $table->unsignedInteger('demos_uploaded')->default(0);
            $table->unsignedInteger('tags_added')->default(0);
            $table->unsignedInteger('alias_reports')->default(0);
            $table->unsignedInteger('demo_assignment_reports')->default(0);

            // Maplists
            $table->unsignedInteger('maplists_created')->default(0);
            $table->unsignedInteger('maplist_maps_added')->default(0);
            $table->unsignedInteger('maplist_likes_received')->default(0);
            $table->unsignedInteger('maplist_favorites_received')->default(0);
            $table->unsignedInteger('play_later_maps')->default(0);

            // Marketplace
            $table->unsignedInteger('marketplace_listings')->default(0);
            $table->unsignedInteger('marketplace_reviews_written')->default(0);
            $table->unsignedInteger('marketplace_reviews_received')->default(0);

            // Headhunter
            $table->unsignedInteger('headhunter_created')->default(0);
            $table->unsignedInteger('headhunter_completed')->default(0);

            // Moderation & content
            $table->unsignedInteger('record_flags')->default(0);
            $table->unsignedInteger('models_uploaded')->default(0);
            $table->unsignedInteger('render_requests')->default(0);

            // Clans
            $table->unsignedInteger('clan_created')->default(0);
            $table->boolean('clan_membership')->default(false);

            // NSFW
            $table->unsignedInteger('nsfw_flags')->default(0);

            // Records & authoring
            $table->unsignedInteger('records_count')->default(0);
            $table->unsignedInteger('maps_authored')->default(0);
            $table->unsignedInteger('models_authored')->default(0);

            // Profile
            $table->unsignedTinyInteger('social_connections')->default(0);
            $table->boolean('profile_avatar')->default(false);
            $table->boolean('profile_background')->default(false);
            $table->boolean('profile_layout_customized')->default(false);
            $table->boolean('name_effect_set')->default(false);
            $table->boolean('avatar_effect_set')->default(false);

            // Donations
            $table->decimal('donation_total_eur', 10, 2)->default(0);

            // Computed scores
            $table->decimal('total_score', 10, 2)->default(0)->index();
            $table->decimal('community_badge_score', 10, 2)->default(0);
            $table->unsignedInteger('rank')->default(0)->index();

            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_helper_scores');
    }
};
