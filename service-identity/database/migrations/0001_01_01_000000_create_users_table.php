<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('identifiant')->unique();
            $table->string('nom');
            $table->string('prenom');
            $table->string('email')->unique();
            $table->string('cin')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->string('telephone')->nullable(); // Compatibility with main
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Role & Status (Using strings for flexibility as per main)
            $table->string('role')->default('agence');
            $table->string('role_type')->default('externe');
            $table->string('statut')->default('pending');

            // Profile Info (From main)
            $table->string('photo')->nullable();
            $table->string('avatar')->nullable();
            $table->string('adresse')->nullable();
            $table->date('date_naissance')->nullable();

            // Access Request logic
            $table->text('access_reason')->nullable();

            // Password Management
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('password_changed_at')->nullable();

            // Two-Factor Authentication
            $table->boolean('two_factor_enabled')->default(false);
            $table->string('two_factor_channel')->default('email');
            $table->string('two_factor_code', 6)->nullable();
            $table->timestamp('two_factor_expires_at')->nullable();
            $table->timestamp('two_factor_verified_at')->nullable();

            // Security Audit
            $table->integer('login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['role', 'statut']);
            $table->index('statut');

        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
