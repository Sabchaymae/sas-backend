<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            // ── Administrateur (2FA activé) ───────────────────────────
            [
                'identifiant'        => 'USR001',
                'prenom'             => 'Admin',
                'nom'                => 'Oriotel',
                'email'              => 'admin@oriotel.com',
                'cin'                => 'AA000001',
                'phone'              => '+212600000001',
                'password'           => Hash::make('Oriotel@2026'),
                'role'               => 'administrateur',
                'role_type'          => 'interne',
                'statut'             => 'active',
                'two_factor_enabled' => true,
                'must_change_password' => false,
                'email_verified_at'  => now(),
            ],
            // ── Animateur (must_change_password) ─────────────────────
            [
                'identifiant'        => 'USR002',
                'prenom'             => 'Yassine',
                'nom'                => 'Animateur',
                'email'              => 'animateur@oriotel.com',
                'cin'                => 'BB000002',
                'phone'              => '+212600000002',
                'password'           => Hash::make('Oriotel@2026'),
                'role'               => 'animateur',
                'role_type'          => 'interne',
                'statut'             => 'active',
                'two_factor_enabled' => false,
                'must_change_password' => true,
                'email_verified_at'  => now(),
            ],
            // ── Agence (login standard) ───────────────────────────────
            [
                'identifiant'        => 'USR003',
                'prenom'             => 'Karim',
                'nom'                => 'Agence',
                'email'              => 'agence@oriotel.com',
                'cin'                => 'CC000003',
                'phone'              => '+212600000003',
                'password'           => Hash::make('Oriotel@2026'),
                'role'               => 'agence',
                'role_type'          => 'externe',
                'statut'             => 'active',
                'two_factor_enabled' => false,
                'must_change_password' => false,
                'email_verified_at'  => now(),
            ],
            // ── Opérateur ─────────────────────────────────────────────
            [
                'identifiant'        => 'USR004',
                'prenom'             => 'Sara',
                'nom'                => 'Operateur',
                'email'              => 'operateur@oriotel.com',
                'cin'                => 'DD000004',
                'phone'              => '+212600000004',
                'password'           => Hash::make('Oriotel@2026'),
                'role'               => 'operateur',
                'role_type'          => 'interne',
                'statut'             => 'active',
                'two_factor_enabled' => false,
                'must_change_password' => false,
                'email_verified_at'  => now(),
            ],
            // ── Compte pending (accès refusé au login) ────────────────
            [
                'identifiant'        => 'USR005',
                'prenom'             => 'Pending',
                'nom'                => 'User',
                'email'              => 'pending@oriotel.com',
                'cin'                => 'EE000005',
                'phone'              => '+212600000005',
                'password'           => Hash::make('Oriotel@2026'),
                'role'               => 'agence',
                'role_type'          => 'externe',
                'statut'             => 'pending',
                'two_factor_enabled' => false,
                'must_change_password' => false,
                'email_verified_at'  => null,
            ],
            // ── Compte suspendu ───────────────────────────────────────
            [
                'identifiant'        => 'USR006',
                'prenom'             => 'Suspended',
                'nom'                => 'User',
                'email'              => 'suspended@oriotel.com',
                'cin'                => 'FF000006',
                'phone'              => '+212600000006',
                'password'           => Hash::make('Oriotel@2026'),
                'role'               => 'animateur',
                'role_type'          => 'interne',
                'statut'             => 'suspended',
                'two_factor_enabled' => false,
                'must_change_password' => false,
                'email_verified_at'  => now(),
            ],
        ];

        foreach ($users as $data) {
            $user = User::withTrashed()->where('email', $data['email'])
                ->orWhere('cin', $data['cin'])
                ->orWhere('identifiant', $data['identifiant'])
                ->first();

            if ($user) {
                $user->fill($data)->save();
            } else {
                User::create($data);
            }
        }

        $this->command->info('✅ ' . count($users) . ' utilisateurs créés/mis à jour.');
    }
}
