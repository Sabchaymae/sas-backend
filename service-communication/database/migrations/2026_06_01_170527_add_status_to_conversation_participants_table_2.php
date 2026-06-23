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
        if (!Schema::hasColumn('conversation_participants', 'status')) {
            Schema::table('conversation_participants', function (Blueprint $table) {
                $table->enum('status', ['active', 'left', 'removed'])->default('active')->after('role');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('conversation_participants', 'status')) {
            Schema::table('conversation_participants', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
