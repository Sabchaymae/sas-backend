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
        Schema::table('task_user', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // On ne remet pas les contraintes car elles sont invalides dans cette architecture
    }
};
