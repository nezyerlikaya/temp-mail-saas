<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $allPermissionIds = Permission::query()->pluck('id')->all();

        foreach (config('permissions.roles', []) as $slug => $definition) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => null,
                    'is_system' => true,
                ],
            );

            $permissions = $definition['permissions'] === ['*']
                ? $allPermissionIds
                : Permission::query()
                    ->whereIn('slug', $definition['permissions'])
                    ->pluck('id')
                    ->all();

            $role->permissions()->sync($permissions);
        }
    }
}
