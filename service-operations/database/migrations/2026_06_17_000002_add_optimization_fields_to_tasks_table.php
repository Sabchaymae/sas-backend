<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'estimated_duration')) {
                $table->decimal('estimated_duration', 5, 2)->default(1.00);
            }
            if (!Schema::hasColumn('tasks', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable();
            }
        });
    }

    public function down() {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['estimated_duration', 'assigned_at']);
        });
    }
};
