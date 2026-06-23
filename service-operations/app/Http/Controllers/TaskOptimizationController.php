<?php
namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\TaskReassignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TaskOptimizationController extends Controller
{
    private $aiServiceUrl;

    public function __construct()
    {
        $this->aiServiceUrl = env('AI_SERVICE_URL', 'http://service-ai-similarity:8000');
    }

    public function getSuggestions(Request $request)
    {
        try {
            // Récupérer les utilisateurs et les tâches
            $users = User::where('statut', 'actif')->get();
            $user = $request->user();
            $query = Task::whereNotIn('status', ['COMPLETED', 'CANCELLED'])->with('users');
            
            if ($user && !$user->isAdmin()) {
                $query->whereHas('users', function($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
            }
            $tasks = $query->get();

            // Préparer les données pour le microservice
            $usersPayload = $users->map(function($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'role' => $u->role
                ];
            })->toArray();
            
            $tasksPayload = $tasks->map(function($t) {
                return [
                    'id' => $t->id,
                    'title' => $t->title,
                    'description' => $t->description,
                    'priority' => $t->priority,
                    'due_date' => $t->due_date,
                    'status' => $t->status,
                    'assigned_at' => $t->assigned_at,
                    'assigned_to' => $t->users->map(function($u) {
                        return [
                            'id' => $u->id,
                            'name' => $u->name,
                            'role' => $u->role
                        ];
                    })->toArray()
                ];
            })->toArray();

            // Appeler le microservice AI
            $response = Http::timeout(120)->post(
                "{$this->aiServiceUrl}/tasks/optimize",
                [
                    'users' => $usersPayload,
                    'tasks' => $tasksPayload
                ]
            );

            if ($response->failed()) {
                \Log::error('AI service failed: ' . $response->body());
                return $this->getFallbackSuggestions($users, $tasks);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            \Log::error('Optimization suggestions failed: ' . $e->getMessage());
            return $this->getFallbackSuggestions($users, $tasks);
        }
    }

    private function getFallbackSuggestions($users, $tasks)
    {
        $reassignments = [];
        $fifteenMinutesAgo = now()->subMinutes(15);
        
        // Calculate workload
        $userWorkload = [];
        foreach ($users as $user) {
            $userTasks = $tasks->filter(fn($t) => $t->users->contains($user->id));
            $score = 0;
            foreach ($userTasks as $t) {
                $score += match($t->priority) {
                    'URGENT' => 5,
                    'HIGH' => 3,
                    'MEDIUM' => 2,
                    'LOW' => 1,
                    default => 1
                };
            }
            $userWorkload[$user->id] = [
                'user' => $user,
                'score' => $score
            ];
        }
        
        $timeoutTasks = $tasks->filter(function($t) use ($fifteenMinutesAgo) {
            return $t->priority === 'URGENT' && $t->status === 'TO_DO' && $t->assigned_at && $t->assigned_at <= $fifteenMinutesAgo;
        });

        foreach ($timeoutTasks as $task) {
            $currentUser = $task->users->first();
            if (!$currentUser) continue;
            
            $sameRoleUsers = $users->filter(fn($u) => $u->role === $currentUser->role && $u->id !== $currentUser->id);
            $bestUser = $sameRoleUsers->sortBy(fn($u) => $userWorkload[$u->id]['score'] ?? 999)->first() ?? $users->first();
            
            if (!$bestUser) continue;
            
            $reassignments[] = [
                'taskId' => $task->id,
                'taskTitle' => $task->title,
                'currentPriority' => $task->priority,
                'currentAssignee' => $currentUser->name,
                'suggestedAssignee' => $bestUser->name,
                'suggestedAssigneeId' => $bestUser->id,
                'reason' => 'Timeout 15min - Tâche Urgente (IA indisponible)',
                'action' => 'block_and_reassign',
                'severity' => 'critical'
            ];
        }

        $sortedWorkload = collect($userWorkload)->sortByDesc('score');
        $mostLoaded = $sortedWorkload->first()['user'] ?? null;
        $leastLoaded = $sortedWorkload->last()['user'] ?? null;
        
        return response()->json([
            'reassignments' => $reassignments,
            'deadlines' => [],
            'summary' => [
                'mostLoadedUser' => $mostLoaded?->name,
                'leastLoadedUser' => $leastLoaded?->name,
                'urgentTasksCount' => $tasks->where('priority', 'URGENT')->count(),
                'delayRiskCount' => count($reassignments),
                'automaticReassignmentsCount' => TaskReassignment::count()
            ],
            'is_fallback' => true
        ]);
    }

    public function applyOptimizations(Request $request)
    {
        try {
            \Log::info('ApplyOptimizations called with: ', $request->all());
            $validated = $request->validate([
                'reassignments' => 'array',
                'deadlines' => 'array'
            ]);

            DB::beginTransaction();
            try {
                foreach ($validated['reassignments'] ?? [] as $index => $sugg) {
                    \Log::info("Processing reassignments[$index]: ", $sugg);
                    $task = Task::find($sugg['taskId']);
                    if (!$task) {
                        \Log::warning("Task with id {$sugg['taskId']} not found!");
                        continue;
                    }

                    $currentUser = $task->users->first();
                    \Log::info("Current assigned user: ", $currentUser ? $currentUser->toArray() : null);

                    $newUser = User::find($sugg['suggestedAssigneeId']);
                    if (!$newUser) {
                        \Log::warning("New user with id {$sugg['suggestedAssigneeId']} not found!");
                        continue;
                    }
                    \Log::info("New user found: ", $newUser->toArray());

                    if (in_array($sugg['action'], ['reassign', 'block_and_reassign'])) {
                        if ($sugg['action'] === 'block_and_reassign') {
                            \Log::info("Marking task as BLOCKED");
                            $task->update(['status' => 'BLOCKED']);
                        }
                        \Log::info("Syncing task {$task->id} to user {$newUser->id}");
                        $task->users()->sync([$newUser->id]);
                        \Log::info("Updating task assigned_at and status to TO_DO");
                        $task->update(['assigned_at' => now(), 'status' => 'TO_DO']);
                        \Log::info("Creating TaskReassignment record");
                        TaskReassignment::create([
                            'task_id' => $task->id,
                            'from_user_id' => $currentUser?->id,
                            'to_user_id' => $newUser->id,
                            'reason' => $sugg['reason']
                        ]);
                    }
                    if ($sugg['action'] === 'prioritize') {
                        \Log::info("Updating task priority to URGENT");
                        $task->update(['priority' => 'URGENT']);
                    }
                }

                foreach ($validated['deadlines'] ?? [] as $index => $sugg) {
                    \Log::info("Processing deadlines[$index]: ", $sugg);
                    $task = Task::find($sugg['taskId']);
                    if ($task) {
                        $task->update(['due_date' => $sugg['newDate']]);
                    }
                }

                DB::commit();
                \Log::info("Optimizations applied successfully!");
                return response()->json(['message' => 'Optimisations appliquées avec succès']);
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error("Error inside transaction: ", ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                throw $e;
            }
        } catch (\Exception $e) {
            \Log::error('Apply optimizations failed: ', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
