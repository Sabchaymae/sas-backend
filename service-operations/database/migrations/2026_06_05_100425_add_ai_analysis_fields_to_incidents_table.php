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
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('ai_site')->nullable();
            $table->string('ai_equipment')->nullable();
            $table->string('ai_category')->nullable();
            $table->string('ai_impacted_service')->nullable();
            $table->string('ai_priority')->nullable();
            $table->float('ai_priority_score')->nullable();
            $table->text('ai_summary')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn([
                'ai_site',
                'ai_equipment',
                'ai_category',
                'ai_impacted_service',
                'ai_priority',
                'ai_priority_score',
                'ai_summary'
            ]);
        });
    }
};
