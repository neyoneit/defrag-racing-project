<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A public line about what a comps donation is for.
 *
 * Separate from `note`, which belongs to the donation as a whole and already
 * shows on the donations page - it tends to carry a thank-you or a payment
 * reference. Sharing one column would put a PayPal reference into the comps
 * panel, or somebody's dedication to a competition onto the card about keeping
 * the site running.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_donations', function (Blueprint $table) {
            $table->string('comps_note', 160)->nullable()->after('comps_start_comp');
        });
    }

    public function down(): void
    {
        Schema::table('site_donations', function (Blueprint $table) {
            $table->dropColumn('comps_note');
        });
    }
};
