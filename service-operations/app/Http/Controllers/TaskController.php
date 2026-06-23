<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Http\Resources\TaskResource;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request) {
        try {
            $user = $request->user();
            $query = Task::with(['users', 'comments.user']);

            // Si l'utilisateur n'est pas admin, il ne voit que ses propres tâches
            if ($user && !$user->isAdmin()) {
                $query->whereHas('users', function($q) use ($user) {
                    $q->where('users.id', $user->id);
                });
            }

            $tasks = $query->orderBy('updated_at', 'desc')->get();
            return TaskResource::collection($tasks);
        } catch (\Exception $e) {
            \Log::error('Task index failed:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        \Log::info('Task creation request:', $request->all());

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:TO_DO,IN_PROGRESS,BLOCKED,COMPLETED,CANCELLED',
            'priority' => 'required|in:LOW,MEDIUM,HIGH,URGENT',
            'due_date' => 'nullable|date',
            'assigned_users' => 'nullable|array',
            'assigned_users.*' => 'integer',
        ]);

        try {
            // Créer la tâche avec tous les champs possibles
            $taskData = $request->only([
                'title', 'description', 'status', 'priority', 'due_date',
                'client_name', 'client_phone', 'client_address', 'client_email',
                'incident_date', 'is_recurring', 'is_incident'
            ]);
            
            $task = Task::create($taskData);

            // Synchroniser les utilisateurs assignés (ils existent dans Identity)
            $assignedUsers = $request->input('assigned_users', []);
            if (!empty($assignedUsers)) {
                $task->users()->sync($assignedUsers);
                $task->update(['assigned_at' => now()]);
            }

            \Log::info('Task created successfully:', ['id' => $task->id]);

            return new TaskResource($task->load('users'));
        } catch (\Exception $e) {
            \Log::error('Task creation failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erreur lors de la création de la tâche',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        \Log::info('Task update request:', ['id' => $id, 'data' => $request->all()]);
        $task = Task::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:TO_DO,IN_PROGRESS,BLOCKED,COMPLETED,CANCELLED',
            'priority' => 'sometimes|required|in:LOW,MEDIUM,HIGH,URGENT',
            'due_date' => 'nullable|date',
            'assigned_users' => 'nullable|array',
            'assigned_users.*' => 'integer',
        ]);

        try {
            $taskData = $request->only([
                'title', 'description', 'status', 'priority', 'due_date',
                'client_name', 'client_phone', 'client_address', 'client_email',
                'incident_date', 'is_recurring', 'is_incident'
            ]);
            $task->update($taskData);

            if ($request->has('assigned_users')) {
                $assignedUsers = $request->input('assigned_users', []);
                $task->users()->sync($assignedUsers);
                $task->update(['assigned_at' => now()]);
            }

            \Log::info('Task updated successfully:', ['id' => $task->id]);

            return new TaskResource($task->load(['users', 'comments.user']));
        } catch (\Exception $e) {
            \Log::error('Task update failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Erreur lors de la mise à jour de la tâche',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $task = Task::findOrFail($id);
        $task->delete();

        return response()->json(['message' => 'Tâche supprimée avec succès']);
    }

    public function storeComment(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $user = $request->user() ?: User::where('role', 'admin')->first();

        $validated = $request->validate([
            'content' => 'required|string',
            'type' => 'required|in:comment,issue',
        ]);

        $comment = $task->comments()->create([
            'user_id' => $user->id,
            'content' => $validated['content'],
            'type' => $validated['type'],
        ]);

        // Mettre à jour le compteur de commentaires si nécessaire
        $task->increment('comments_count');

        return response()->json([
            'success' => true,
            'comment' => $comment->load('user')
        ]);
    }
}
