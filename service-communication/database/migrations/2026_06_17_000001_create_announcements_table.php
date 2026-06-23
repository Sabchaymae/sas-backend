<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('author_id');        // user who created it
            $table->string('author_name');                  // denormalized for display
            $table->string('title');
            $table->text('body');
            $table->enum('type', ['event', 'meeting', 'notice', 'urgent'])->default('notice');
            $table->string('color', 7)->default('#1428C9'); // hex colour for card accent
            $table->boolean('pinned')->default(false);
            $table->timestamp('expires_at');               // mandatory expiry
            $table->timestamps();
        });

        Schema::create('announcement_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->string('emoji', 10);                   // 👍 ❤️ 😂 etc.
            $table->timestamps();
            $table->unique(['announcement_id', 'user_id']); // one reaction per user
        });

        Schema::create('announcement_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->onDelete('cascade');
            $table->unsignedBigInteger('user_id');
            $table->string('user_name');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_comments');
        Schema::dropIfExists('announcement_reactions');
        Schema::dropIfExists('announcements');
    }
};
