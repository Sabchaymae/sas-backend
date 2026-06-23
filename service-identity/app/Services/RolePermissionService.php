<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RolePermissionService
{
    /**
     * Get all roles with user count.
     * Auto-syncs roles from the users.role column into the roles table.
     */
    public function getAllRoles(): Collection
    {
<<<<<<< HEAD
        // 0. Ensure default roles and permissions exist
        if (Role::count() < 5 || Permission::count() === 0) {
            $this->seedDefaults();
        }

=======
>>>>>>> import/master
        // 1. Sync: find all unique role values from users table and ensure they exist in roles table
        $userRoles = User::whereNotNull('role')
            ->where('role', '!=', '')
            ->distinct()
            ->pluck('role');

        foreach ($userRoles as $roleName) {
            Role::firstOrCreate(
                ['slug' => Str::slug($roleName)],
                [
                    'name'  => ucfirst($roleName),
                    'slug'  => Str::slug($roleName),
                    'color' => 'bg-gray-700',
                ]
            );
        }

        // 2. Return all roles with user count (from both pivot table and users.role column)
        $roles = Role::orderBy('created_at', 'desc')->get();

        foreach ($roles as $role) {
            // Count users linked via pivot table OR via the direct role column
            $pivotCount = $role->users()->count();
            $directCount = User::where('role', $role->slug)
                ->whereNotIn('id', $role->users()->pluck('users.id')->toArray())
                ->count();
            $role->users_count = $pivotCount + $directCount;
        }

        return $roles;
    }

    /**
     * Create a new role.
     */
    public function createRole(array $data): Role
    {
        return Role::create([
            'name'  => $data['name'],
            'slug'  => Str::slug($data['name']),
            'color' => $data['color'] ?? 'bg-gray-700',
        ]);
    }

    /**
     * Update an existing role.
     */
    public function updateRole(int $id, array $data): Role
    {
        $role = Role::findOrFail($id);
        $role->update([
            'name'  => $data['name'] ?? $role->name,
            'slug'  => isset($data['name']) ? Str::slug($data['name']) : $role->slug,
            'color' => $data['color'] ?? $role->color,
        ]);
        return $role;
    }

    /**
     * Delete a role.
     */
    public function deleteRole(int $id): bool
    {
        $role = Role::findOrFail($id);
        // Optional: check if users are assigned to this role before deleting
        // or reassign them to a default role.
        return $role->delete();
    }

    /**
     * Get all users for a specific role (from pivot table + users.role column).
     */
    public function getUsersByRole(int $roleId, string $search = ''): Collection
    {
        $role = Role::find($roleId);
        if (!$role) {
            return collect();
        }

        // Get user IDs from pivot table
        $pivotUserIds = $role->users()->pluck('users.id')->toArray();

        // Get user IDs from direct role column
        $directUserIds = User::where('role', $role->slug)
            ->whereNotIn('id', $pivotUserIds)
            ->pluck('id')
            ->toArray();

        $allUserIds = array_merge($pivotUserIds, $directUserIds);

        $query = User::whereIn('id', $allUserIds);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('prenom', 'like', "%{$search}%")
                  ->orWhere('nom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->select('id', 'prenom', 'nom', 'email', 'statut', 'photo', 'role')->get();
    }

    /**
     * Get the permission matrix (all modules/actions) with current status for target(s).
     */
    public function getPermissionMatrix(int $roleId, array $userIds = []): array
    {
<<<<<<< HEAD
        if (Permission::count() === 0) {
            $this->seedDefaults();
        }

=======
>>>>>>> import/master
        $allPermissions = Permission::all();
        
        $currentPermissions = collect();
        
        if (!empty($userIds)) {
            // Check if ANY of the selected users have individual overrides
            $hasOverrides = User::whereIn('id', $userIds)
                ->whereHas('permissions')
                ->exists();

            if ($hasOverrides) {
                // Get UNION of individual permissions for these users
                $currentPermissions = Permission::whereHas('users', function ($q) use ($userIds) {
                    $q->whereIn('users.id', $userIds);
                })->pluck('slug');
            } else {
                // FALLBACK: If no user overrides found, show the role's permissions
                $role = Role::find($roleId);
                if ($role) {
                    $currentPermissions = $role->permissions()->pluck('slug');
                }
            }
        } else {
            // Get permissions for the role
            $role = Role::find($roleId);
            if ($role) {
                $currentPermissions = $role->permissions()->pluck('slug');
            }
        }

        $matrix = [];
        foreach ($allPermissions as $p) {
            $matrix[$p->module][$p->action] = $currentPermissions->contains($p->slug);
        }

        return $matrix;
    }

    /**
     * Sync permissions for a role or specific users.
     */
    public function syncPermissions(int $roleId, array $userIds, array $permissionMatrix): bool
    {
<<<<<<< HEAD
        if (Permission::count() === 0) {
            $this->seedDefaults();
        }

=======
>>>>>>> import/master
        // 1. Flatten the matrix to get active slugs
        $activeSlugs = [];
        foreach ($permissionMatrix as $module => $actions) {
            foreach ($actions as $action => $enabled) {
                if ($enabled) {
                    $activeSlugs[] = Str::slug($module . ' ' . $action, '.');
                }
            }
        }

        $permissionIds = Permission::whereIn('slug', $activeSlugs)->pluck('id')->toArray();

        if (!empty($userIds)) {
            // Sync individual overrides for these users
            foreach ($userIds as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $user->permissions()->sync($permissionIds);
                }
            }
        } else {
            // Sync role permissions
            $role = Role::find($roleId);
            if ($role) {
                $role->permissions()->sync($permissionIds);
            }
        }

        return true;
    }

    /**
     * Seed initial data (for demo/development).
     */
    public function seedDefaults(): void
    {
        // Default Roles
        $roles = [
            ['name' => 'Administrateur', 'slug' => 'administrateur', 'color' => 'bg-indigo-500'],
            ['name' => 'Assistant', 'slug' => 'assistant', 'color' => 'bg-emerald-500'],
            ['name' => 'Animateur', 'slug' => 'animateur', 'color' => 'bg-amber-500'],
            ['name' => 'Compteur', 'slug' => 'compteur', 'color' => 'bg-rose-500'],
            ['name' => 'Superviseur', 'slug' => 'superviseur', 'color' => 'bg-blue-500'],
        ];

        foreach ($roles as $r) {
            Role::updateOrCreate(['slug' => $r['slug']], $r);
        }

        // Default Modules and Actions (from PermissionMatrix.jsx)
<<<<<<< HEAD
        $modules = ['Utilisateurs', 'Souscriptions', 'Stock', 'Comptabilité', 'Tâches', 'Incidents', 'Temps', 'Agences', 'Communication', 'Autorisation'];
=======
        $modules = ['Utilisateurs', 'Souscriptions', 'Stock', 'Comptabilité', 'Tâches', 'Temps', 'Agences', 'Communication', 'Autorisation'];
>>>>>>> import/master
        $actions = ['Lecture', 'Création', 'Modification', 'Suppression', 'Validation', 'Export'];

        foreach ($modules as $m) {
            foreach ($actions as $a) {
                $slug = Str::slug($m . ' ' . $a, '.');
                Permission::firstOrCreate(['slug' => $slug], [
                    'name'   => $a,
                    'module' => $m,
                    'action' => $a,
                ]);
            }
        }
<<<<<<< HEAD

        // Assign all permissions to Administrateur
        $admin = Role::where('slug', 'administrateur')->first();
        if ($admin) {
            $admin->permissions()->sync(Permission::pluck('id'));
        }

        // Assign default permissions to Assistant (Incidents: Lecture)
        $assistant = Role::where('slug', 'assistant')->first();
        if ($assistant) {
            $assistantPermissions = Permission::where('module', 'Incidents')
                ->where('action', 'Lecture')
                ->pluck('id');
            $assistant->permissions()->sync($assistantPermissions);
        }
=======
>>>>>>> import/master
    }
}
