<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The original migration used foreignId() which creates FK constraints
 * pointing to users(id) in the same DB. Since users live in oriotel_identity
 * (cross-database), we drop those FK constraints and keep plain unsigned
 * bigint columns instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_rejoin_requests', function (Blueprint $table) {
            // Drop FK constraints added by the original migration
            $table->dropForeign(['user_id']);
            $table->dropForeign(['admin_id']);
            $table->dropForeign(['conversation_id']);
        });

        Schema::table('group_rejoin_requests', function (Blueprint $table) {
            // Re-add conversation_id FK (same DB — safe)
            $table->foreign('conversation_id')
                  ->references('id')
                  ->on('conversations')
                  ->onDelete('cascade');
            // user_id and admin_id are cross-DB — no FK, just plain columns
        });
    }

    public function down(): void
    {
        // Nothing to reverse — original state is wrong anyway
    }
};
