<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_rejoin_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');   // who wants to rejoin
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null'); // admin who handled it
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();

            // Only one pending request per user per group at a time
            $table->unique(['conversation_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_rejoin_requests');
    }
};
