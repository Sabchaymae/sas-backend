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
        Schema::table('tasks', function (Blueprint $col) {
            $col->string('client_name')->nullable();
            $col->string('client_phone')->nullable();
            $col->text('client_address')->nullable();
            $col->string('client_email')->nullable();
            $col->dateTime('incident_date')->nullable();
            $col->boolean('is_recurring')->default(false);
            $col->boolean('is_incident')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $col) {
            $col->dropColumn([
                'client_name',
                'client_phone',
                'client_address',
                'client_email',
                'incident_date',
                'is_recurring',
                'is_incident'
            ]);
        });
    }
};
