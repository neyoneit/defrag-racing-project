<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comps - the automatic competition.
 *
 * Ten tables in one migration rather than ten files, because they only make
 * sense together and half of them carry a foreign key into another: created
 * separately, the order becomes something you have to get right by picking
 * timestamps, which is a worse way to express "these depend on each other"
 * than simply writing them in order.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One instance of the competition. Weekly runs a single round, season
        // runs five - the difference lives here rather than in two sets of
        // tables, because everything below behaves identically either way.
        Schema::create('comps', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['weekly', 'season'])->index();
            $table->unsignedInteger('number');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->enum('status', ['upcoming', 'active', 'finished'])->default('upcoming')->index();
            $table->timestamps();

            // Weekly #12 and Season #12 are different competitions.
            $table->unique(['type', 'number']);
        });

        Schema::create('comp_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comp_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('index');
            $table->enum('category', ['strafe', 'weapon', 'combo']);

            // Which gun, on a weapon round. The map is then picked from those
            // carrying exactly that one.
            $table->string('weapon', 8)->nullable();

            $table->timestamp('voting_opens_at');
            $table->timestamp('voting_closes_at');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->enum('status', ['voting', 'locked', 'active', 'finished'])->default('voting')->index();
            $table->timestamps();

            $table->unique(['comp_id', 'index']);
        });

        // The five maps on the ballot.
        Schema::create('comp_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comp_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('map_id')->constrained('maps')->cascadeOnDelete();

            // Denormalised counters so a ballot renders without counting rows.
            $table->unsignedInteger('votes_cpm')->default(0);
            $table->unsignedInteger('votes_vq3')->default(0);

            // The physics this map CANNOT be voted in, i.e. the one it cannot
            // be finished in. A map tagged cpmonly blocks 'vq3'. Null is the
            // normal case: playable in both, on both ballots.
            $table->enum('blocked_physics', ['cpm', 'vq3'])->nullable();

            $table->timestamps();

            $table->unique(['comp_round_id', 'map_id']);
        });

        // The winning map, one row per physics. Two ballots can land on the
        // same map or on different ones; both are ordinary outcomes.
        //
        // This table is also the answer to "has this map been played before?",
        // which is why nothing else records it. A map that only ever appeared
        // as a losing candidate is not in here and may come round again.
        Schema::create('comp_round_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comp_round_id')->constrained()->cascadeOnDelete();
            $table->enum('physics', ['cpm', 'vq3']);
            $table->foreignId('map_id')->constrained('maps');

            // vote     - most votes on that ballot
            // wildcard - a holder named it, first come first served
            // carried  - this physics cast no votes at all and took the other
            //            physics' map rather than drawing at random
            // random   - nobody voted in either physics, so there was nothing
            //            to carry
            $table->enum('decided_by', ['vote', 'wildcard', 'carried', 'random']);
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->unique(['comp_round_id', 'physics']);
            $table->index('map_id');
        });

        Schema::create('comp_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comp_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comp_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('physics', ['cpm', 'vq3']);

            // created_at is load-bearing: when two maps tie on combined votes,
            // the one that reached that count first wins.
            $table->timestamps();

            $table->unique(['comp_round_id', 'user_id', 'physics']);
        });

        // The right to name a round's map outright.
        Schema::create('comp_wildcards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Winners come in pairs, one per physics, so the right does too and
            // is spent on the ballot of its own physics.
            $table->enum('physics', ['cpm', 'vq3']);

            $table->enum('source', ['season_win', 'five_weekly_wins']);
            $table->foreignId('source_comp_id')->nullable()->constrained('comps')->nullOnDelete();

            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_on_round_id')->nullable()->constrained('comp_rounds')->nullOnDelete();
            $table->foreignId('used_map_id')->nullable()->constrained('maps')->nullOnDelete();

            $table->timestamps();

            // "Does this person hold an unspent wildcard right now?"
            $table->index(['user_id', 'used_at']);
        });

        // Uploaded runs. Several rows per person is the normal case - people
        // improve through the round and only their best valid time counts, so
        // there is deliberately no unique key holding them to one attempt.
        Schema::create('comp_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comp_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('mdd_id')->nullable()->index();
            // Null until the demo has been parsed. Nobody is asked which
            // physics they ran: the demo says so, and asking only creates a
            // question somebody can answer wrongly about their own file.
            $table->enum('physics', ['cpm', 'vq3'])->nullable();

            // Zero until the demo has been parsed - see the status below.
            $table->unsignedInteger('time')->default(0);

            // uploaded_demos, not demos - the latter belongs to the tournament
            // section and is keyed to a tournament round. What people upload
            // through the site generally lands in uploaded_demos, and that is
            // what a comps entry is: an ordinary upload that also happens to
            // be an entry.
            $table->foreignId('uploaded_demo_id')->constrained('uploaded_demos')->cascadeOnDelete();

            $table->boolean('is_online')->default(false);

            // Uploaded as a curiosity rather than an entry. Kept off the
            // leaderboard entirely.
            $table->boolean('is_highlight')->default(false);

            // Uploading is asynchronous: the file is stored and queued, and a
            // job parses the map, physics and time out of it afterwards. An
            // entry therefore exists before anybody knows what it contains, so
            // it starts pending and only counts once the parse agrees it is a
            // run of this round's map in this physics.
            $table->enum('status', ['pending', 'valid', 'invalid'])->default('pending')->index();

            // Why it was rejected, for the person who uploaded it. Wrong map,
            // wrong physics, unparseable, or an admin upholding a report.
            $table->string('invalid_reason')->nullable();

            // Set when an online demo happens to line up with a scraped record.
            // A bonus for the site; comps neither needs it nor waits for it.
            $table->unsignedBigInteger('matched_record_id')->nullable();

            $table->timestamps();

            // The leaderboard query, in one index.
            $table->index(['comp_round_id', 'physics', 'status', 'time']);
        });

        Schema::create('comp_demo_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comp_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->enum('status', ['open', 'upheld', 'dismissed'])->default('open')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // Somebody found a candidate they cannot finish in one of the physics.
        // Upheld, it becomes a cpmonly / vq3only tag on the map.
        Schema::create('comp_map_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comp_round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('map_id')->constrained('maps')->cascadeOnDelete();
            $table->enum('physics', ['cpm', 'vq3']);
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['open', 'approved', 'rejected'])->default('open')->index();
            $table->timestamps();

            $table->unique(['comp_round_id', 'map_id', 'physics', 'reported_by'], 'comp_map_reports_unique');
        });

        // Standings of a finished round, frozen. Season adds them up across
        // its five rounds; weekly has the one.
        Schema::create('comp_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comp_round_id')->constrained()->cascadeOnDelete();
            $table->enum('physics', ['cpm', 'vq3']);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Equal times share a rank rather than being split by upload time.
            $table->unsignedInteger('rank');
            $table->unsignedInteger('time');

            // Decimal because tied places share the points for the places they
            // occupy: two people second gets each (18 + 15) / 2 = 16.5.
            $table->decimal('points', 6, 1)->default(0);

            $table->timestamps();

            $table->unique(['comp_round_id', 'physics', 'user_id']);
        });
    }

    public function down(): void
    {
        // Reverse creation order - the foreign keys demand it.
        Schema::dropIfExists('comp_results');
        Schema::dropIfExists('comp_map_reports');
        Schema::dropIfExists('comp_demo_reports');
        Schema::dropIfExists('comp_submissions');
        Schema::dropIfExists('comp_wildcards');
        Schema::dropIfExists('comp_votes');
        Schema::dropIfExists('comp_round_maps');
        Schema::dropIfExists('comp_candidates');
        Schema::dropIfExists('comp_rounds');
        Schema::dropIfExists('comps');
    }
};
