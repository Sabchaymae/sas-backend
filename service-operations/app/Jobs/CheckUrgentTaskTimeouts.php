<?php
namespace App\Jobs;
use App\Models\Task;
use App\Models\TaskReassignment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckUrgentTaskTimeouts implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle() {
        Log::info('Checking urgent task timeouts...');

        $tasks = Task::where('priority', 'URGENT')
            ->whereIn('status', ['TO_DO'])
            ->whereNotNull('assigned_at')
            ->get();

        foreach ($tasks as $task) {
            if (!$task->is_timeout) continue;

            $currentUser = $task->users->first();
            if (!$currentUser) continue;

            $bestUser = $this->findBestCandidate($currentUser->role, $task->estimated_duration);
            if (!$bestUser) continue;

            $task->users()->sync([$bestUser->id]);
            $task->update(['assigned_at' => now()]);

            TaskReassignment::create([
                'task_id' => $task->id,
                'from_user_id' => $currentUser->id,
                'to_user_id' => $bestUser->id,
                'reason' => 'Timeout 15min - Tâche Urgente'
            ]);

            Log::info("Reassigned task {$task->id} from {$currentUser->name} to {$bestUser->name}");
        }
    }

    private function findBestCandidate($role, $taskDuration) {
        return User::where('role', $role)
            ->where('statut', 'actif')
            ->get()
            ->sortBy(function($user) use ($taskDuration) {
                return $user->workload_score + ($user->daily_workload + $taskDuration);
            })
            ->first();
    }
}
