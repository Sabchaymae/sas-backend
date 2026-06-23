<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE conversation_participants MODIFY COLUMN status ENUM('active', 'left', 'removed', 'group_deleted') NOT NULL DEFAULT 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE conversation_participants MODIFY COLUMN status ENUM('active', 'left', 'removed') NOT NULL DEFAULT 'active'");
    }
};
