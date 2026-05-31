<?php

namespace Tests\Feature;

use App\Enums\StaffStatus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOperationsCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_routes_are_protected(): void
    {
        $this->get('/admin')->assertForbidden();
        $this->get('/admin/operations')->assertForbidden();
        $this->get('/admin/health')->assertForbidden();
        $this->get('/admin/queue')->assertForbidden();
        $this->get('/admin/domains')->assertForbidden();
        $this->get('/admin/abuse')->assertForbidden();
        $this->get('/admin/billing')->assertForbidden();
        $this->get('/admin/audit')->assertForbidden();
    }

    public function test_permissions_are_enforced(): void
    {
        $staff = $this->staffWithPermissions([]);

        $this->actingAs($staff, 'staff')
            ->get('/admin/operations')
            ->assertForbidden();
    }

    public function test_operations_dashboard_loads(): void
    {
        $this->actingAs($this->staffWithPermissions(['operations.view']), 'staff')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Operations Center')
            ->assertSee('Queue pending');

        $this->actingAs($this->staffWithPermissions(['operations.view']), 'staff')
            ->get('/admin/operations')
            ->assertOk()
            ->assertSee('Operations Center');
    }

    public function test_health_center_loads(): void
    {
        $this->actingAs($this->staffWithPermissions(['health.view']), 'staff')
            ->get('/admin/health')
            ->assertOk()
            ->assertSee('Health Center');
    }

    public function test_queue_center_loads(): void
    {
        $this->actingAs($this->staffWithPermissions(['queue.view']), 'staff')
            ->get('/admin/queue')
            ->assertOk()
            ->assertSee('Queue Center');
    }

    public function test_domains_center_loads(): void
    {
        $this->actingAs($this->staffWithPermissions(['domains.view']), 'staff')
            ->get('/admin/domains')
            ->assertOk()
            ->assertSee('Domain Center');
    }

    public function test_abuse_center_loads(): void
    {
        $this->actingAs($this->staffWithPermissions(['abuse.view']), 'staff')
            ->get('/admin/abuse')
            ->assertOk()
            ->assertSee('Abuse Center');
    }

    public function test_billing_center_loads(): void
    {
        $this->actingAs($this->staffWithPermissions(['billing.view']), 'staff')
            ->get('/admin/billing')
            ->assertOk()
            ->assertSee('Billing Center');
    }

    public function test_audit_center_loads(): void
    {
        $this->actingAs($this->staffWithPermissions(['audit.view']), 'staff')
            ->get('/admin/audit')
            ->assertOk()
            ->assertSee('Audit Center');
    }

    private function staffWithPermissions(array $permissions): StaffUser
    {
        $staff = StaffUser::query()->create([
            'name' => 'Operations Staff',
            'email' => uniqid('staff-', true).'@example.com',
            'password' => Hash::make('password'),
            'status' => StaffStatus::Active,
        ]);

        $role = Role::query()->create([
            'name' => 'Operations Role',
            'slug' => uniqid('operations-role-', false),
            'is_system' => false,
        ]);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $slug,
                    'group' => str($slug)->before('.')->toString(),
                ],
            );

            $role->permissions()->attach($permission);
        }

        $staff->roles()->attach($role);

        return $staff;
    }
}
