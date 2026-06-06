<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_similarities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incident_id');
            $table->unsignedBigInteger('similar_incident_id');
            $table->float('score'); // Score de similarité (0.0 à 1.0)
            $table->timestamps();

            $table->foreign('incident_id')->references('id')->on('incidents')->onDelete('cascade');
            $table->foreign('similar_incident_id')->references('id')->on('incidents')->onDelete('cascade');
            
            // Éviter les doublons de comparaison
            $table->unique(['incident_id', 'similar_incident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_similarities');
    }
};
