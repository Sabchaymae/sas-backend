<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RolePermissionService;
use App\Services\ActivityLogService;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RolePermissionController extends Controller
{
    public function __construct(
        protected RolePermissionService $service
    ) {}

    /**
     * Get all roles.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'roles'   => $this->service->getAllRoles()
        ]);
    }

    /**
     * Create a new role.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name'  => 'required|string|max:255|unique:roles,name',
                'color' => 'sometimes|string|max:50'
            ]);

            $role = $this->service->createRole($request->only('name', 'color'));
            
            ActivityLogService::log(
                ActivityLog::TYPE_CREATION,
                "Création de rôle",
                ActivityLog::MODULE_USERS,
                "Nouveau rôle créé : {$role->name}",
                null,
                ['role_id' => $role->id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Rôle créé avec succès.',
                'role'    => $role
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Role creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du rôle.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing role.
     */
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name'  => 'sometimes|string|max:255|unique:roles,name,' . $id,
                'color' => 'sometimes|string|max:50'
            ]);

            $role = $this->service->updateRole($id, $request->only('name', 'color'));
            
            ActivityLogService::modification(
                "Modification de rôle",
                ActivityLog::MODULE_USERS,
                "Rôle mis à jour : {$role->name}"
            );

            return response()->json([
                'success' => true,
                'message' => 'Rôle mis à jour avec succès.',
                'role'    => $role
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour du rôle.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a role.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $roleName = \App\Models\Role::find($id)?->name ?? "Inconnu (#$id)";
            $this->service->deleteRole($id);

            ActivityLogService::log(
                ActivityLog::TYPE_SUPPRESSION,
                "Suppression de rôle",
                ActivityLog::MODULE_USERS,
                "Rôle supprimé : {$roleName}"
            );

            return response()->json([
                'success' => true,
                'message' => 'Rôle supprimé avec succès.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la suppression du rôle.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get users for a specific role.
     */
    public function roleUsers(int $id, Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'users'   => $this->service->getUsersByRole($id, $request->query('search', ''))
        ]);
    }

    /**
     * Get permission matrix for the currently authenticated user.
     * No params needed — the role is resolved from the user's token.
     */
    public function myPermissions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        // Administrateurs : tous les droits
        if (strtolower($user->role) === \App\Models\User::ROLE_ADMIN) {
            $allPermissions = \App\Models\Permission::all();
            $matrix = [];
            foreach ($allPermissions as $p) {
                $matrix[$p->module][$p->action] = true;
            }
            return response()->json(['success' => true, 'permissions' => $matrix, 'is_admin' => true]);
        }

        // Trouver le rôle de l'utilisateur dans la table roles (par slug)
        $role = \App\Models\Role::where('slug', strtolower($user->role))
            ->orWhere('name', $user->role)
            ->first();

        if (!$role) {
            // Rôle inconnu → aucun droit
            return response()->json(['success' => true, 'permissions' => [], 'is_admin' => false]);
        }

        $matrix = $this->service->getPermissionMatrix($role->id, [$user->id]);

        return response()->json([
            'success'     => true,
            'permissions' => $matrix,
            'is_admin'    => false,
        ]);
    }

    /**
     * Get permission matrix for a target.
     */
    public function getPermissions(Request $request): JsonResponse
    {
        $request->validate([
            'role_id'  => 'required|exists:roles,id',
            'user_ids' => 'sometimes|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        return response()->json([
            'success'     => true,
            'permissions' => $this->service->getPermissionMatrix(
                $request->role_id,
                $request->user_ids ?? []
            )
        ]);
    }


    /**
     * Save permissions for a target.
     */
    public function syncPermissions(Request $request): JsonResponse
    {
        $request->validate([
            'role_id'      => 'required|exists:roles,id',
            'user_ids'     => 'sometimes|array',
            'permissions'  => 'required|array'
        ]);

        $this->service->syncPermissions(
            $request->role_id,
            $request->user_ids ?? [],
            $request->permissions
        );

        $target = count($request->user_ids ?? []) > 0 
            ? count($request->user_ids) . " utilisateur(s)" 
            : "Rôle ID " . $request->role_id;

        ActivityLogService::validation(
            "Mise à jour des permissions",
            ActivityLog::MODULE_SECURITY,
            "Permissions synchronisées pour : {$target}."
        );

        return response()->json([
            'success' => true,
            'message' => 'Permissions synchronisées avec succès.'
        ]);
    }

    /**
     * Helper to seed development data.
     */
    public function seed(): JsonResponse
    {
        $this->service->seedDefaults();
        return response()->json(['success' => true, 'message' => 'Données de base créées.']);
    }
}
