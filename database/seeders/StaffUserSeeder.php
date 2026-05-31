<?php

namespace Database\Seeders;

use App\Enums\StaffStatus;
use App\Models\Role;
use App\Models\StaffUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $email = env('LOCAL_STAFF_EMAIL');
        $password = env('LOCAL_STAFF_PASSWORD');

        if (blank($email) || blank($password)) {
            return;
        }

        $staff = StaffUser::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => env('LOCAL_STAFF_NAME', 'Local Staff'),
                'password' => Hash::make($password),
                'status' => StaffStatus::Active,
                'password_changed_at' => now(),
            ],
        );

        $superAdmin = Role::query()->where('slug', 'super_admin')->first();

        if ($superAdmin !== null) {
            $staff->roles()->syncWithoutDetaching([$superAdmin->id]);
        }
    }
}
