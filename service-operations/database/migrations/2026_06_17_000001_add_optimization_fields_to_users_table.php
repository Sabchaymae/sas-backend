<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'daily_capacity')) {
                $table->decimal('daily_capacity', 5, 2)->default(8.00);
            }
            if (!Schema::hasColumn('users', 'avg_task_completion_time')) {
                $table->decimal('avg_task_completion_time', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('users', 'tasks_completed_per_day')) {
                $table->integer('tasks_completed_per_day')->default(0);
            }
            if (!Schema::hasColumn('users', 'delay_rate')) {
                $table->decimal('delay_rate', 5, 2)->default(0.00);
            }
        });
    }

    public function down() {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['daily_capacity', 'avg_task_completion_time', 'tasks_completed_per_day', 'delay_rate']);
        });
    }
};
