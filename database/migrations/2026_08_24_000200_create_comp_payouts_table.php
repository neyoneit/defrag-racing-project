<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What happened to a week's prize money.
 *
 * One row per physics per finished round, because that is how the prize is
 * won: each physics has its own winner and its own amount, and one of them
 * being paid says nothing about the other.
 *
 * The amount is copied in rather than read back off the round. A round quotes
 * what it pays while it is being played, and an admin correcting that figure
 * afterwards must not silently rewrite what somebody was already handed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comp_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comp_round_id')->constrained()->cascadeOnDelete();
            $table->enum('physics', ['cpm', 'vq3']);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Euro. The comps pool has no currency logic anywhere else either.
            $table->decimal('amount', 8, 2)->default(0);

            $table->enum('status', ['pending', 'paid', 'donated_site', 'donated_comps'])
                ->default('pending')
                ->index();

            // The donation this prize became, when it was given back. Kept so
            // the row can point at where the money actually went instead of
            // only saying that it went somewhere.
            $table->foreignId('site_donation_id')->nullable()
                ->constrained('site_donations')->nullOnDelete();

            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->text('note')->nullable();

            $table->timestamps();

            // A tie shares a physics, so the winner is part of the key.
            $table->unique(['comp_round_id', 'physics', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comp_payouts');
    }
};
