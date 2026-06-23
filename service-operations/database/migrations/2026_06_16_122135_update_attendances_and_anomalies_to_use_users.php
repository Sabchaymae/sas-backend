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
        // Update attendances table
        Schema::table('attendances', function (Blueprint $table) {
            // Drop foreign key and employee_id column
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
            
            // Add user_id column
            $table->unsignedBigInteger('user_id')->after('id');
        });

        // Update attendance_anomalies table
        Schema::table('attendance_anomalies', function (Blueprint $table) {
            // Drop foreign key and employee_id column
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
            
            // Add user_id column
            $table->unsignedBigInteger('user_id')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert attendances table
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->unsignedBigInteger('employee_id')->after('id');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });

        // Revert attendance_anomalies table
        Schema::table('attendance_anomalies', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->unsignedBigInteger('employee_id')->after('id');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }
};
