<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Création d'un administrateur de test pour l'automatisation PFE
        User::updateOrCreate(
            ['email' => 'admin@oriotel.ma'],
            [
                'name' => 'Admin Oriotel',
                'password' => Hash::make('password'),
                'role' => 'admin'
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        User::where('email', 'admin@oriotel.ma')->delete();
    }
};
