<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Services\ActivityLogService;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(\Illuminate\Http\Request $request): AnonymousResourceCollection
    {
        $users = $this->userService->getAllUsers($request->all());
        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        Log::info('User Creation Attempt', $request->validated());
        
        try {
            $user = $this->userService->createUser($request->validated());
            Log::info('User Created Successfully', ['id' => $user->id]);
            
            ActivityLogService::log(
                ActivityLog::TYPE_CREATION,
                "Création d'utilisateur",
                ActivityLog::MODULE_USERS,
                "Nouvel utilisateur créé : {$user->full_name} ({$user->role}). Identifiant: {$user->identifiant}",
                null,
                ['user_id' => $user->id, 'email' => $user->email, 'identifiant' => $user->identifiant]
            );

            return (new UserResource($user))
                ->response()
                ->setStatusCode(201);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1062) {
                return response()->json(['message' => 'Une donnée unique (email, téléphone ou identifiant) est déjà utilisée.'], 422);
            }
            return response()->json(['message' => 'Erreur base de données: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::error('User Creation Failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Erreur lors de la création: ' . $e->getMessage()], 500);
        }
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $updatedUser = $this->userService->updateUser($user, $request->validated());
        
        ActivityLogService::modification(
            "Modification d'utilisateur",
            ActivityLog::MODULE_USERS,
            "Données mises à jour pour {$user->full_name}."
        );

        return new UserResource($updatedUser);
    }

    public function destroy(User $user): JsonResponse
    {
        $userName = $user->full_name;
        $this->userService->deleteUser($user);

        ActivityLogService::log(
            ActivityLog::TYPE_SUPPRESSION,
            "Suppression d'utilisateur",
            ActivityLog::MODULE_USERS,
            "L'utilisateur {$userName} a été supprimé."
        );

        return response()->json(null, 204);
    }
}
