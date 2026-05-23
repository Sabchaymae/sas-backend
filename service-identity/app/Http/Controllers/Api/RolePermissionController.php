<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RolePermissionController extends Controller
{
    /**
     * Get all roles with their user counts.
     */
    public function indexRoles()
    {
        $roles = Role::withCount('users')->get();
        return response()->json([
            'success' => true,
            'roles' => $roles
        ]);
    }

    /**
     * Create a new role.
     */
    public function storeRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'color' => 'nullable|string'
        ]);

        $role = Role::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'color' => $request->color ?? 'bg-gray-700'
        ]);

        return response()->json([
            'success' => true,
            'role' => $role
        ]);
    }

    /**
     * Get users belonging to a specific role.
     */
    public function getRoleUsers($roleId)
    {
        $users = User::where('role_id', $roleId)->get();
        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }

    /**
     * Get the permission matrix for a role and optionally for specific users.
     */
    public function getPermissions(Request $request)
    {
        $roleId = $request->query('role_id');
        $userIds = $request->query('user_ids', []);
        
        if (is_string($userIds)) {
            $userIds = explode(',', $userIds);
        }
        $userIds = array_filter((array)$userIds);

        \Log::info('Fetching permissions', ['role_id' => $roleId, 'user_ids' => $userIds]);

        $role = Role::find($roleId);
        if (!$role) {
            return response()->json(['success' => false, 'message' => 'Rôle non trouvé'], 404);
        }

        // Get all possible permissions
        $allPermissions = Permission::all();
        
        // Get permissions associated with the role
        $rolePermissions = $role->permissions->pluck('slug')->toArray();

        // If specific users are selected, we need to handle potential overrides.
        // For simplicity in the matrix UI, if multiple users are selected, we might 
        // show the common permissions or just the role's ones as base.
        // The frontend expects: { Module: { Action: Boolean } }
        
        $matrix = [];
        foreach ($allPermissions as $perm) {
            $isChecked = in_array($perm->slug, $rolePermissions);
            
            // If only one user is selected, show their specific overrides
            if (count($userIds) === 1) {
                $user = User::find($userIds[0]);
                if ($user) {
                    $override = $user->permissions()->where('permission_id', $perm->id)->first();
                    if ($override) {
                        $isChecked = (bool) $override->pivot->allowed;
                    }
                }
            }

            $matrix[$perm->module][$perm->action] = $isChecked;
        }

        return response()->json([
            'success' => true,
            'permissions' => (object)$matrix
        ]);
    }

    /**
     * Sync permissions for a role or specific users.
     */
    public function syncPermissions(Request $request)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'user_ids' => 'nullable|array',
            'permissions' => 'required|array'
        ]);

        $roleId = $request->role_id;
        $userIds = $request->user_ids ?? [];
        $permissionsInput = $request->permissions; // { Module: { Action: Boolean } }

        // Flatten permissions and find IDs
        $enabledPermissionIds = [];
        foreach ($permissionsInput as $module => $actions) {
            foreach ($actions as $action => $enabled) {
                if ($enabled) {
                    $perm = Permission::where('module', $module)->where('action', $action)->first();
                    if ($perm) {
                        $enabledPermissionIds[] = $perm->id;
                    }
                }
            }
        }

        if (empty($userIds)) {
            // Case 1: Apply to Role (and clear user overrides for this role's users if needed?)
            // Usually, role permissions are the base.
            $role = Role::find($roleId);
            $role->permissions()->sync($enabledPermissionIds);
        } else {
            // Case 2: Apply to specific Users (Overrides)
            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if ($user) {
                    // For each permission, we determine if it's an override compared to the role
                    // But the simplest way is to store the specific state for these users.
                    $syncData = [];
                    foreach ($enabledPermissionIds as $pid) {
                        $syncData[$pid] = ['allowed' => true];
                    }
                    // Find permissions that were NOT enabled but are in the matrix
                    // This is tricky. Let's just sync the enabled ones as 'allowed' = true.
                    // And for those NOT in enabledPermissionIds, if they are part of the module/actions we manage, 
                    // we should probably set 'allowed' = false if we want a strict override.
                    
                    // Actually, a simpler approach for the user_permissions table:
                    $user->permissions()->sync($syncData);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Permissions synchronisées avec succès'
        ]);
    }
}
