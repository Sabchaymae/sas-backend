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
        // Step 1: Expand enum to include both old and new values
        DB::statement("ALTER TABLE conversation_participants MODIFY COLUMN status ENUM('pending', 'joined', 'active', 'left', 'removed') NOT NULL DEFAULT 'joined'");
        
        // Step 2: Update existing data
        DB::table('conversation_participants')
            ->where('status', 'joined')
            ->update(['status' => 'active']);
        
        // Step 3: Shrink enum to only include new values
        DB::statement("ALTER TABLE conversation_participants MODIFY COLUMN status ENUM('active', 'left', 'removed') NOT NULL DEFAULT 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Expand enum to include both old and new values
        DB::statement("ALTER TABLE conversation_participants MODIFY COLUMN status ENUM('pending', 'joined', 'active', 'left', 'removed') NOT NULL DEFAULT 'active'");
        
        // Step 2: Revert data
        DB::table('conversation_participants')
            ->where('status', 'active')
            ->update(['status' => 'joined']);
        
        // Step 3: Shrink enum to only include old values
        DB::statement("ALTER TABLE conversation_participants MODIFY COLUMN status ENUM('pending', 'joined') NOT NULL DEFAULT 'joined'");
    }
};
