<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persisted DefragLive chat / console stream.
 *
 * The Python WebSocket bridge POSTs every broadcast it persisted to
 * console.json (message / command / ext_command / afk_notification /
 * afk_help / server_record_celebration) into the web instead, making the DB
 * the source of truth so the stream can be read + moderated from Filament and
 * fed to the giveaway feature. The full original broadcast object is kept in
 * `payload` so the public console.json the legacy Twitch extension reads can
 * be reproduced byte-faithfully from the DB (see DefragliveJsonWriter).
 *
 * Content already passes the bot's filters.py blacklist BEFORE it reaches us;
 * we store and serve it verbatim and never read anything pre-filter, so
 * automatic moderation is unaffected by this integration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defraglive_chat_messages', function (Blueprint $table) {
            $table->id();

            // The bot assigns each message a blake2b id (random-salted, so NOT
            // reproducible). We store it as received and reuse it verbatim to
            // target overlay deletes via the ext_command/delete_message the
            // current extension already honours.
            $table->string('message_id', 32)->nullable()->index();

            // Broadcast action - mirrors the bridge's save_message() set.
            $table->string('action', 32)->index();

            $table->string('author')->nullable();
            $table->text('content')->nullable();

            // Float unix timestamp from the bot payload (its own clock).
            $table->double('msg_timestamp')->nullable();

            // Full original broadcast object {action, message:{...}} for
            // faithful console.json reproduction - nothing is lost.
            $table->json('payload');

            // Identity resolution for the giveaway feature - deferred, so
            // nullable for now (NameMatcher pass fills these later).
            $table->unsignedBigInteger('resolved_user_id')->nullable()->index();
            $table->unsignedBigInteger('resolved_mdd_id')->nullable();

            // Manual moderation = soft delete. Excluded from the served
            // console.json on the next rewrite; admin can restore.
            $table->softDeletes();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defraglive_chat_messages');
    }
};
