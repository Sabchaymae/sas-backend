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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Actor
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name');         // full name snapshot at log time
            $table->string('user_role');         // role snapshot at log time

            // Action classification
            $table->enum('type', [
                'connexion',
                'modification',
                'souscription',
                'validation',
                'anomalie',
                'refus',
            ])->index();

            $table->string('action');            // short human-readable description
            $table->string('module')->index();   // ex: Auth, Users, Subscriptions …
            $table->text('detail')->nullable();  // full description

            // Context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('properties')->nullable(); // extra structured data

            $table->timestamps();

            // Indexes for common query patterns
            $table->index(['type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['module', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
