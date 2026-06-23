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
        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'created_at', 'user_id'], 'idx_messages_unread_count');
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->index(['user_id', 'last_read_at'], 'idx_participants_user_read');
            $table->index(['conversation_id', 'user_id'], 'idx_participants_conv_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_unread_count');
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropIndex('idx_participants_user_read');
            $table->dropIndex('idx_participants_conv_user');
        });
    }
};
