<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions — important when re-running
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define granular permissions
        $permissions = [
            // Works
            'view works', 'create works', 'edit works', 'delete works', 'merge works',
            // Editions
            'view editions', 'create editions', 'edit editions', 'delete editions',
            // Authors
            'view authors', 'create authors', 'edit authors', 'delete authors', 'merge authors',
            // Publishers
            'view publishers', 'create publishers', 'edit publishers', 'delete publishers',
            // Review Queue
            'view review queue', 'process review queue',
            // Ingestion
            'view ingestion', 'manage ingestion',
            // Users & Roles (admin only)
            'view users', 'manage users',
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
        }

        // cataloger: read everything + process review queue
        $cataloger = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'cataloger']);
        $cataloger->syncPermissions([
            'view works', 'view editions', 'view authors', 'view publishers',
            'view review queue', 'process review queue',
        ]);

        // editor: cataloger + full CRUD on catalog entities
        $editor = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'editor']);
        $editor->syncPermissions([
            'view works', 'create works', 'edit works',
            'view editions', 'create editions', 'edit editions',
            'view authors', 'create authors', 'edit authors',
            'view publishers', 'create publishers', 'edit publishers',
            'view review queue', 'process review queue',
            'view ingestion',
        ]);

        // admin: editor + destructive actions + ingestion management
        $admin = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(\Spatie\Permission\Models\Permission::where('name', '!=', 'manage users')->pluck('name'));

        // super_admin gets every permission via Gate::before() — no need to assign individually
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);
    }
}
