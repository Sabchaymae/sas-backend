<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deleted_conversations', function (Blueprint $table) {
            // Timestamp de la suppression — sert d'horizon pour filtrer les messages
            $table->timestamp('deleted_at')->nullable()->after('conversation_id');
            // true  = conversation cachée (supprimée, pas encore de nouveau message)
            // false = réapparue pour cet utilisateur (nouveaux messages depuis la suppression)
            $table->boolean('is_hidden')->default(true)->after('deleted_at');
        });

        // Backfill : les entrées existantes n'ont pas de deleted_at, on met created_at
        \DB::statement('UPDATE deleted_conversations SET deleted_at = created_at WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('deleted_conversations', function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'is_hidden']);
        });
    }
};
