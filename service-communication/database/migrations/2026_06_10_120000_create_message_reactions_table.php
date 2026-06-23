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
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messages')->onDelete('cascade');
            $table->unsignedBigInteger('user_id'); // No FK - user is in identity service
            $table->string('emoji', 10); // Store emoji character (e.g., 👍, ❤️, 😂)
            $table->timestamps();

            // A user can only have one reaction per message
            // But can change the emoji
            $table->unique(['message_id', 'user_id']);

            // Index for performance
            $table->index(['message_id', 'emoji']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
