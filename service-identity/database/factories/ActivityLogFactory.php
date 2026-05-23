<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    private static array $samples = [
        [
            'type'   => ActivityLog::TYPE_CONNEXION,
            'action' => 'Connexion réussie',
            'module' => ActivityLog::MODULE_AUTH,
            'detail' => 'L\'utilisateur s\'est connecté avec succès.',
        ],
        [
            'type'   => ActivityLog::TYPE_CONNEXION,
            'action' => 'Déconnexion',
            'module' => ActivityLog::MODULE_AUTH,
            'detail' => 'L\'utilisateur a clôturé sa session.',
        ],
        [
            'type'   => ActivityLog::TYPE_CONNEXION,
            'action' => 'Vérification 2FA réussie',
            'module' => ActivityLog::MODULE_AUTH,
            'detail' => 'Code de double authentification validé.',
        ],
        [
            'type'   => ActivityLog::TYPE_MODIFICATION,
            'action' => 'Mise à jour du profil',
            'module' => ActivityLog::MODULE_PROFILE,
            'detail' => 'L\'utilisateur a modifié ses informations personnelles.',
        ],
        [
            'type'   => ActivityLog::TYPE_MODIFICATION,
            'action' => 'Changement de mot de passe',
            'module' => ActivityLog::MODULE_SECURITY,
            'detail' => 'Le mot de passe a été modifié avec succès.',
        ],
        [
            'type'   => ActivityLog::TYPE_SOUSCRIPTION,
            'action' => 'Nouvelle souscription créée',
            'module' => ActivityLog::MODULE_SUBSCRIPTIONS,
            'detail' => 'Souscription au forfait Pro initiée.',
        ],
        [
            'type'   => ActivityLog::TYPE_VALIDATION,
            'action' => 'Compte utilisateur validé',
            'module' => ActivityLog::MODULE_USERS,
            'detail' => 'L\'administrateur a approuvé le compte.',
        ],
        [
            'type'   => ActivityLog::TYPE_ANOMALIE,
            'action' => 'Tentative de connexion échouée',
            'module' => ActivityLog::MODULE_SECURITY,
            'detail' => 'Mauvais mot de passe — tentatives consécutives détectées.',
        ],
        [
            'type'   => ActivityLog::TYPE_ANOMALIE,
            'action' => 'Compte verrouillé',
            'module' => ActivityLog::MODULE_SECURITY,
            'detail' => 'Compte verrouillé après trop de tentatives infructueuses.',
        ],
        [
            'type'   => ActivityLog::TYPE_REFUS,
            'action' => 'Accès refusé',
            'module' => ActivityLog::MODULE_SECURITY,
            'detail' => 'Tentative d\'accès sans droits suffisants.',
        ],
    ];

    public function definition(): array
    {
        $sample = $this->faker->randomElement(self::$samples);

        return [
            'user_id'    => User::factory(),
            'user_name'  => $this->faker->name(),
            'user_role'  => $this->faker->randomElement([
                User::ROLE_ADMIN,
                User::ROLE_ANIMATEUR,
                User::ROLE_AGENCE,
                User::ROLE_OPERATEUR,
            ]),
            'type'       => $sample['type'],
            'action'     => $sample['action'],
            'module'     => $sample['module'],
            'detail'     => $sample['detail'],
            'ip_address' => $this->faker->localIpv4(),
            'user_agent' => $this->faker->userAgent(),
            'properties' => null,
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'updated_at' => now(),
        ];
    }

    // ─── Named States ──────────────────────────────────────────────────

    public function connexion(): static
    {
        return $this->state([
            'type'   => ActivityLog::TYPE_CONNEXION,
            'action' => 'Connexion réussie',
            'module' => ActivityLog::MODULE_AUTH,
        ]);
    }

    public function anomalie(): static
    {
        return $this->state([
            'type'   => ActivityLog::TYPE_ANOMALIE,
            'action' => 'Tentative de connexion échouée',
            'module' => ActivityLog::MODULE_SECURITY,
        ]);
    }

    public function refus(): static
    {
        return $this->state([
            'type'   => ActivityLog::TYPE_REFUS,
            'action' => 'Accès refusé',
            'module' => ActivityLog::MODULE_SECURITY,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state([
            'user_id'   => $user->id,
            'user_name' => $user->full_name,
            'user_role' => $user->role,
        ]);
    }
}
