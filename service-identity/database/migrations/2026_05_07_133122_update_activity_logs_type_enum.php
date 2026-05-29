<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL to modify the ENUM column as Laravel's Blueprint doesn't handle ENUM changes easily
        DB::statement("ALTER TABLE activity_logs MODIFY COLUMN type ENUM('connexion', 'creation', 'modification', 'suppression', 'souscription', 'validation', 'anomalie', 'refus') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE activity_logs MODIFY COLUMN type ENUM('connexion', 'modification', 'souscription', 'validation', 'anomalie', 'refus') NOT NULL");
    }
};
