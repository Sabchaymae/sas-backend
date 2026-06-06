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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->text('content')->nullable();
            $table->enum('type', ['text', 'file', 'system'])->default('text');
            $table->boolean('is_edited')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['conversation_id', 'created_at']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
