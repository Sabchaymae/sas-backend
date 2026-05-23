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
            $table->string('cin_recto_path')->nullable()->after('client_cin');
            $table->string('cin_verso_path')->nullable()->after('cin_recto_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('souscriptions', function (Blueprint $table) {
            $table->dropColumn(['cin_recto_path', 'cin_verso_path']);
        });
    }
};
