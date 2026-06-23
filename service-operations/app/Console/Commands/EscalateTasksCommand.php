<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Models\TaskReassignment;
use App\Events\TaskEscalatedEvent;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EscalateTasksCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tasks:escalate';

    /**
     * The console command description.
     */
    protected $description = 'Escalade automatique des tâches urgentes non traitées après 10 secondes (pour test) avec réaffectation automatique';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Démarrage du moteur d\'escalade et réaffectation...');

        // Seuil de 10 secondes d'inactivité (pour test) - au lieu de 15 minutes
        $threshold = Carbon::now()->subSeconds(10);

        $this->comment("Recherche des tâches URGENT en TO_DO inactives depuis : " . $threshold->toDateTimeString());

        $tasksToEscalate = Task::where('priority', 'URGENT')
            ->where('status', 'TO_DO')
            ->where('assigned_at', '<=', $threshold)
            ->with('users')
            ->get();

        if ($tasksToEscalate->isEmpty()) {
            $this->info('Aucune tâche à escalader.');
            return;
        }

        // Récupérer tous les utilisateurs actifs
        $activeUsers = User::where('statut', 'actif')->get();

        foreach ($tasksToEscalate as $task) {
            $this->info("Escalade de la tâche #{$task->id} : {$task->title}");

            $currentUser = $task->users->first();
            if (!$currentUser) {
                $this->warning("Aucun utilisateur assigné à la tâche #{$task->id}, passage en BLOCKED");
                $task->status = 'BLOCKED';
                $task->description = $task->description . "\n\n[SYSTÈME] : Escalade automatique après 10s sans assignation.";
                $task->save();
                continue;
            }

            $this->info("Utilisateur actuel: {$currentUser->name} (role: {$currentUser->role})");

            // Trouver les utilisateurs avec le même rôle
            $sameRoleUsers = $activeUsers->filter(function($u) use ($currentUser) {
                return $u->role === $currentUser->role && $u->id !== $currentUser->id;
            });

            if ($sameRoleUsers->isEmpty()) {
                $this->warning("Aucun autre utilisateur avec le rôle {$currentUser->role}, passage en BLOCKED");
                $task->status = 'BLOCKED';
                $task->description = $task->description . "\n\n[SYSTÈME] : Escalade automatique après 10s - aucun autre utilisateur avec le même rôle disponible.";
                $task->save();
                continue;
            }

            // Calculer la charge de travail pour chaque utilisateur du même rôle
            $usersWithWorkload = $sameRoleUsers->map(function($user) {
                return [
                    'user' => $user,
                    'workload' => $user->workload_score
                ];
            });

            // Trouver l'utilisateur avec la charge minimale
            $bestUser = $usersWithWorkload->sortBy('workload')->first()['user'];
            $this->info("Réaffectation à: {$bestUser->name} (charge: {$bestUser->workload_score})");

            DB::beginTransaction();
            try {
                // Créer une réaffectation
                TaskReassignment::create([
                    'task_id' => $task->id,
                    'from_user_id' => $currentUser->id,
                    'to_user_id' => $bestUser->id,
                    'reason' => 'Réaffectation automatique après 10s d\'inactivité'
                ]);

                // Réassigner la tâche
                $task->users()->sync([$bestUser->id]);
                $task->assigned_at = now();
                $task->description = $task->description . "\n\n[SYSTÈME] : Réaffectation automatique de {$currentUser->name} à {$bestUser->name} après 10s d'inactivité.";
                $task->save();

                DB::commit();
                $this->info("Tâche #{$task->id} réaffectée avec succès!");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Erreur lors de la réaffectation: " . $e->getMessage());
                $task->status = 'BLOCKED';
                $task->description = $task->description . "\n\n[SYSTÈME] : Erreur lors de la réaffectation, passage en BLOCKED.";
                $task->save();
            }

            // Déclencher le broadcast
            try {
                broadcast(new TaskEscalatedEvent($task));
                $this->info("Broadcast envoyé pour la tâche #{$task->id}");
            } catch (\Exception $e) {
                $this->error("Erreur de broadcast pour la tâche #{$task->id} : " . $e->getMessage());
            }
        }

        $this->info($tasksToEscalate->count() . ' tâches ont été traitées avec succès.');
    }
}
