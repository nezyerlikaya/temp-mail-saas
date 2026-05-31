<?php

namespace Tests\Feature;

use App\Enums\StaffStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffUser;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffRbacFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_and_rbac_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('staff_users'));
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('permission_role'));
        $this->assertTrue(Schema::hasTable('role_staff_user'));

        $this->assertTrue(Schema::hasColumns('staff_users', [
            'name',
            'email',
            'password',
            'status',
            'last_login_at',
            'last_seen_at',
            'password_changed_at',
            'two_factor_enabled',
            'two_factor_confirmed_at',
            'remember_token',
        ]));
    }

    public function test_role_permission_relationship_works(): void
    {
        $role = Role::query()->create([
            'name' => 'Support',
            'slug' => 'support',
            'is_system' => true,
        ]);
        $permission = Permission::query()->create([
            'name' => 'View users',
            'slug' => 'users.view',
            'group' => 'users',
        ]);

        $role->permissions()->attach($permission);

        $this->assertTrue($role->permissions()->where('slug', 'users.view')->exists());
        $this->assertTrue($permission->roles()->where('slug', 'support')->exists());
    }

    public function test_staff_role_relationship_and_helpers_work(): void
    {
        $staff = StaffUser::query()->create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password' => Hash::make('password'),
            'status' => StaffStatus::Active,
        ]);
        $role = Role::query()->create([
            'name' => 'Moderator',
            'slug' => 'moderator',
            'is_system' => true,
        ]);
        $permission = Permission::query()->create([
            'name' => 'Manage abuse',
            'slug' => 'abuse.manage',
            'group' => 'abuse',
        ]);

        $role->permissions()->attach($permission);
        $staff->roles()->attach($role);

        $this->assertTrue($staff->isActive());
        $this->assertTrue($staff->hasRole('moderator'));
        $this->assertTrue($staff->hasPermission('abuse.manage'));
        $this->assertFalse($staff->hasPermission('settings.manage'));
    }

    public function test_super_admin_role_receives_all_permissions(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $permissionCount = Permission::query()->count();
        $superAdmin = Role::query()->where('slug', 'super_admin')->firstOrFail();

        $this->assertGreaterThan(0, $permissionCount);
        $this->assertSame($permissionCount, $superAdmin->permissions()->count());
    }

    public function test_admin_route_exists_and_is_reserved(): void
    {
        $this->get('/admin')->assertForbidden();
    }

    public function test_normal_user_auth_and_public_routes_still_work(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/')->assertOk();
        $this->getJson('/health')->assertOk();
        $this->get('/status')->assertOk();
        $this->get('/up')->assertOk();
    }
}
