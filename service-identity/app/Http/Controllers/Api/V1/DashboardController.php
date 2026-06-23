<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function getKPIs()
    {
        $data = [
            'activeUsers' => [
                'total' => 124,
                'change' => 12.5,
                'trend' => 'up'
            ],
            'activeFolders' => [
                'count' => 456,
                'change' => 8.3,
                'trend' => 'up'
            ],
            'activeAlerts' => [
                'count' => 23,
                'severity' => 'high'
            ],
            'overdueTasks' => [
                'count' => 12,
                'percentage' => 8.5
            ],
            'newMessages' => [
                'count' => 34,
                'change' => -5.2,
                'trend' => 'down'
            ]
        ];

        return response()->json($data);
    }

    public function getAlerts()
    {
        $alerts = [
            [
                'id' => 1,
                'type' => 'inactive_user',
                'title' => 'Utilisateur inactif',
                'description' => 'Karim Benzema n’a pas pointé depuis 3 jours',
                'priority' => 'high',
                'date' => now()->subHour()->toISOString(),
                'icon' => 'user',
                'color' => 'red',
                'action' => 'Voir profil'
            ],
            [
                'id' => 2,
                'type' => 'blocked_folder',
                'title' => 'Dossier bloqué',
                'description' => 'Dossier #F-2024-123 nécessite une validation',
                'priority' => 'medium',
                'date' => now()->subHours(2)->toISOString(),
                'icon' => 'folder',
                'color' => 'orange',
                'action' => 'Ouvrir dossier'
            ],
            [
                'id' => 3,
                'type' => 'rejected_subscription',
                'title' => 'Souscription rejetée',
                'description' => 'Demande d’agence ABC a été refusée',
                'priority' => 'low',
                'date' => now()->subHours(3)->toISOString(),
                'icon' => 'credit-card',
                'color' => 'blue',
                'action' => 'Voir détails'
            ]
        ];

        return response()->json($alerts);
    }

    public function getActivity()
    {
        $activities = [
            [
                'id' => 1,
                'type' => 'login',
                'user' => 'Admin Oriotel',
                'action' => 'Connexion réussie',
                'time' => 'Il y a 5 min'
            ],
            [
                'id' => 2,
                'type' => 'create_folder',
                'user' => 'Yassine Animateur',
                'action' => 'Création dossier #F-2024-567',
                'time' => 'Il y a 12 min'
            ],
            [
                'id' => 3,
                'type' => 'validation',
                'user' => 'Sarah Opérateur',
                'action' => 'Validation dossier #F-2024-456',
                'time' => 'Il y a 25 min'
            ],
            [
                'id' => 4,
                'type' => 'assignment',
                'user' => 'Admin Oriotel',
                'action' => 'Affectation tâche #T-2024-89 à Karim',
                'time' => 'Il y a 1h'
            ],
            [
                'id' => 5,
                'type' => 'rejection',
                'user' => 'Sarah Opérateur',
                'action' => 'Rejet demande #D-2024-145',
                'time' => 'Il y a 2h'
            ]
        ];

        return response()->json($activities);
    }

    public function getPerformance(Request $request)
    {
        $filter = $request->query('filter', 'day');

        $generateData = function ($count, $nameCallback) {
            $data = [];
            for ($i = 0; $i < $count; $i++) {
                $data[] = [
                    'name' => $nameCallback($i),
                    'folders' => rand(10, 70),
                    'subscriptions' => rand(5, 40),
                    'validation' => rand(85, 98),
                    'productivity' => rand(80, 95)
                ];
            }
            return $data;
        };

        $performance = match ($filter) {
            'week' => $generateData(5, fn($i) => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven'][$i]),
            'month' => $generateData(4, fn($i) => 'Sem ' . ($i + 1)),
            'year' => $generateData(5, fn($i) => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai'][$i]),
            default => $generateData(6, fn($i) => sprintf('%02d:00', 8 + $i * 2))
        };

        return response()->json($performance);
    }

    public function getAISuggestions()
    {
        $suggestions = [
            [
                'id' => 1,
                'type' => 'follow_up',
                'title' => 'Utilisateurs à relancer',
                'description' => '5 utilisateurs n’ont pas complété leur profil depuis plus de 7 jours',
                'score' => 0.92,
                'explanation' => 'Basé sur le taux de complétion et l’historique d’activité',
                'action' => 'Envoyer rappels'
            ],
            [
                'id' => 2,
                'type' => 'risk',
                'title' => 'Dossiers à risque',
                'description' => '12 dossiers risquent de dépasser la date butoir',
                'score' => 0.87,
                'explanation' => 'Analyse des délais moyens et de la charge de travail',
                'action' => 'Prioriser'
            ],
            [
                'id' => 3,
                'type' => 'overload',
                'title' => 'Agents surchargés',
                'description' => 'Sarah Opérateur a une charge 35% supérieure à la moyenne',
                'score' => 0.81,
                'explanation' => 'Comparaison avec la charge opérationnelle moyenne',
                'action' => 'Réallouer'
            ]
        ];

        return response()->json($suggestions);
    }

    public function getCharts()
    {
        $charts = [
            'agency' => [
                ['name' => 'Casablanca', 'value' => 35],
                ['name' => 'Rabat', 'value' => 25],
                ['name' => 'Marrakech', 'value' => 20],
                ['name' => 'Tanger', 'value' => 12],
                ['name' => 'Fès', 'value' => 8]
            ],
            'region' => [
                ['name' => 'Centre', 'value' => 40],
                ['name' => 'Nord', 'value' => 30],
                ['name' => 'Sud', 'value' => 20],
                ['name' => 'Est', 'value' => 10]
            ],
            'status' => [
                ['name' => 'Validé', 'value' => 65],
                ['name' => 'En cours', 'value' => 20],
                ['name' => 'Rejeté', 'value' => 10],
                ['name' => 'Bloqué', 'value' => 5]
            ],
            'operator' => [
                ['name' => 'Sarah', 'value' => 45],
                ['name' => 'Yassine', 'value' => 35],
                ['name' => 'Karim', 'value' => 20]
            ]
        ];

        return response()->json($charts);
    }

    public function getSystemHealth()
    {
        $health = [
            'api' => [
                'status' => 'online',
                'responseTime' => '45ms',
                'uptime' => '99.9%'
            ],
            'database' => [
                'status' => 'online',
                'responseTime' => '12ms',
                'uptime' => '99.9%'
            ],
            'redis' => [
                'status' => 'online',
                'responseTime' => '5ms',
                'uptime' => '99.9%'
            ],
            'ai' => [
                'status' => 'online',
                'responseTime' => '150ms',
                'uptime' => '99.5%'
            ],
            'docker' => [
                'status' => 'online',
                'responseTime' => '10ms',
                'uptime' => '99.8%'
            ],
            'zkteco' => [
                'status' => 'online',
                'responseTime' => '35ms',
                'uptime' => '98.5%'
            ]
        ];

        return response()->json($health);
    }
}
