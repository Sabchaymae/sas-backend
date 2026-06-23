<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'Utilisateurs', 'Souscriptions', 'Stock', 'Comptabilité', 
            'Tâches', 'Temps', 'Agences', 'Communication', 'Autorisation'
        ];

        $actions = [
            'Lecture', 'Création', 'Modification', 'Suppression', 'Validation', 'Export'
        ];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::updateOrCreate(
                    ['slug' => Str::slug($module . ' ' . $action, '.')],
                    [
                        'name' => $action . ' - ' . $module,
                        'module' => $module,
                        'action' => $action,
                    ]
                );
            }
        }
    }
}
