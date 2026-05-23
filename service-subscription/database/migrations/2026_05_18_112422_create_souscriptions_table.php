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
        Schema::create('souscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('client_nom', 100);
            $table->string('client_prenom', 100);
            $table->string('client_cin', 20);
            $table->string('client_telephone', 20)->nullable();
            $table->date('date_naissance')->nullable();
            $table->text('adresse')->nullable();
            $table->string('operateur', 50)->nullable(); // IAM, Inwi, Orange
            $table->string('type_objectif', 50)->nullable();
            $table->string('statut', 50)->nullable(); // brouillon, en_attente, en_cours...
            $table->dateTime('date_soumission')->nullable();
            $table->text('motif_refus')->nullable();
            $table->integer('agent_id')->nullable();
            $table->integer('agence_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('souscriptions');
    }
};
