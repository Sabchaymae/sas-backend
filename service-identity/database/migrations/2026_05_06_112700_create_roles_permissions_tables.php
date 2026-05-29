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
        // ─── ROLES ───────────────────────────────────────────────────
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('color')->default('bg-gray-500');
            $table->timestamps();
        });

        // ─── PERMISSIONS ─────────────────────────────────────────────
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');         // e.g. Lecture
            $table->string('slug')->unique(); // e.g. utilisateurs.lecture
            $table->string('module');       // e.g. Utilisateurs
            $table->string('action');       // e.g. Lecture
            $table->timestamps();
        });

        // ─── PIVOT: ROLE <-> PERMISSION ──────────────────────────────
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->primary(['role_id', 'permission_id']);
        });

        // ─── PIVOT: USER <-> ROLE ───────────────────────────────────
        Schema::create('user_has_roles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->primary(['user_id', 'role_id']);
        });

        // ─── PIVOT: USER <-> PERMISSION (INDIVIDUAL OVERRIDES) ───
        Schema::create('user_has_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->primary(['user_id', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_has_permissions');
        Schema::dropIfExists('user_has_roles');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
