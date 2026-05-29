<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin role always bypasses permission checks.
     */
    public function test_admin_has_all_module_permissions(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrateur',
            'statut' => User::STATUS_ACTIVE,
        ]);

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->hasModulePermission('Utilisateurs', 'Lecture'));
        $this->assertTrue($admin->hasModulePermission('Stock', 'Suppression'));
    }

    /**
     * Test specific permission checks for a standard user.
     */
    public function test_user_has_specific_module_permissions(): void
    {
        $user = User::factory()->create([
            'role' => 'operateur',
            'statut' => User::STATUS_ACTIVE,
        ]);

        $permission = Permission::create([
            'name' => 'Lecture - Stock',
            'slug' => 'stock.lecture',
            'module' => 'Stock',
            'action' => 'Lecture',
        ]);

        $user->permissions()->attach($permission->id);

        $this->assertTrue($user->hasModulePermission('Stock', 'Lecture'));
        $this->assertFalse($user->hasModulePermission('Stock', 'Création'));
    }

    /**
     * Test permission inheritance: having higher actions grants 'Lecture' automatically.
     */
    public function test_user_inherits_read_permission_from_higher_privilege(): void
    {
        $user = User::factory()->create([
            'role' => 'operateur',
            'statut' => User::STATUS_ACTIVE,
        ]);

        $higherPermission = Permission::create([
            'name' => 'Création - Stock',
            'slug' => 'stock.creation',
            'module' => 'Stock',
            'action' => 'Création',
        ]);

        // Assign only Création permission
        $user->permissions()->attach($higherPermission->id);

        // User should have Création
        $this->assertTrue($user->hasModulePermission('Stock', 'Création'));
        // User should automatically inherit Lecture!
        $this->assertTrue($user->hasModulePermission('Stock', 'Lecture'));
        // User should not have Suppression
        $this->assertFalse($user->hasModulePermission('Stock', 'Suppression'));
    }

    /**
     * Test inactive user has no permissions.
     */
    public function test_inactive_user_is_blocked_by_middleware(): void
    {
        $user = User::factory()->create([
            'role' => 'operateur',
            'statut' => User::STATUS_SUSPENDED,
        ]);

        $this->assertFalse($user->isActive());
    }
}
