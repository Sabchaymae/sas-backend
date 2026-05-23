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
        Schema::table('souscriptions', function (Blueprint $table) {
            $table->string('numero_ligne_fixe', 50)->nullable()->after('type_objectif');
            $table->string('contrat_path')->nullable()->after('motif_refus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('souscriptions', function (Blueprint $table) {
            $table->dropColumn(['numero_ligne_fixe', 'contrat_path']);
        });
    }
};
