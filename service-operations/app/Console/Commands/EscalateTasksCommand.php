<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Events\TaskEscalatedEvent;
use Illuminate\Console\Command;
use Carbon\Carbon;

class EscalateTasksCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tasks:escalate';

    /**
     * The console command description.
     */
    protected $description = 'Escalade automatique des tâches urgentes non traitées après 15 minutes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Démarrage du moteur d\'escalade...');

        // Seuil de 15 minutes d'inactivité
        $threshold = Carbon::now()->subMinutes(15);

        $this->comment("Recherche des tâches URGENT en TO_DO inactives depuis : " . $threshold->toDateTimeString());

        $tasksToEscalate = Task::where('priority', 'URGENT')
            ->where('status', 'TO_DO')
            ->where('updated_at', '<=', $threshold)
            ->get();

        if ($tasksToEscalate->isEmpty()) {
            $this->info('Aucune tâche à escalader.');
            return;
        }

        foreach ($tasksToEscalate as $task) {
            $this->info("Escalade de la tâche #{$task->id} : {$task->title}");

            // Action : Passer en BLOQUÉE (Escalade N2)
            $task->status = 'BLOCKED';
            $task->description = $task->description . "\n\n[SYSTÈME] : Escalade automatique N2 après 15min d'inactivité.";
            $task->save();

            // Déclencher le broadcast
            try {
                broadcast(new TaskEscalatedEvent($task));
                $this->info("Broadcast envoyé pour la tâche #{$task->id}");
            } catch (\Exception $e) {
                $this->error("Erreur de broadcast pour la tâche #{$task->id} : " . $e->getMessage());
            }
        }

        $this->info($tasksToEscalate->count() . ' tâches ont été escaladées avec succès.');
    }
}
