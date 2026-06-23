<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop existing unique indexes if they exist to start fresh
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_phone_unique');
            });
        } catch (\Exception $e) { /* Ignore if not exists */ }

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_telephone_unique');
            });
        } catch (\Exception $e) { /* Ignore if not exists */ }

        // 2. Clean up duplicate 'telephone' entries
        $duplicatesTel = Illuminate\Support\Facades\DB::table('users')
            ->select('telephone')
            ->whereNotNull('telephone')
            ->groupBy('telephone')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('telephone');

        foreach ($duplicatesTel as $tel) {
            $users = Illuminate\Support\Facades\DB::table('users')->where('telephone', $tel)->get();
            foreach ($users->skip(1) as $user) {
                Illuminate\Support\Facades\DB::table('users')
                    ->where('id', $user->id)
                    ->update(['telephone' => $tel . '_DUP_' . $user->id]);
            }
        }

        // 3. Clean up duplicate 'phone' entries
        $duplicatesPhone = Illuminate\Support\Facades\DB::table('users')
            ->select('phone')
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone');

        foreach ($duplicatesPhone as $p) {
            $users = Illuminate\Support\Facades\DB::table('users')->where('phone', $p)->get();
            foreach ($users->skip(1) as $user) {
                Illuminate\Support\Facades\DB::table('users')
                    ->where('id', $user->id)
                    ->update(['phone' => $p . '_DUP_' . $user->id]);
            }
        }

        // 4. Apply changes and create indexes
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
            $table->string('telephone')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone', 'users_phone_unique');
            $table->unique('telephone', 'users_telephone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropUnique(['telephone']);
        });
    }
};
