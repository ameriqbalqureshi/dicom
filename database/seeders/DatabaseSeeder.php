<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Package;
use App\Models\User;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with one account per role, each on their role's trial, and example packages.
     */
    public function run(SubscriptionManager $subscriptions): void
    {
        Package::factory()->forRole(Role::Physician)->create([
            'name' => 'Physician Monthly',
            'description' => 'For cardiologists reporting regularly.',
            'price' => 5000,
            'duration_days' => 30,
            'max_uploads' => 100,
            'max_storage_mb' => 20480,
            'max_reports' => null,
            'max_shares' => null,
            'max_guest_links' => 100,
        ]);

        Package::factory()->forRole(Role::Technician)->create([
            'name' => 'Technician Monthly',
            'description' => 'For cath labs sharing studies with physicians.',
            'price' => 4000,
            'duration_days' => 30,
            'max_uploads' => 200,
            'max_storage_mb' => 51200,
            'max_reports' => 0,
            'max_shares' => null,
            'max_guest_links' => 0,
        ]);

        Package::factory()->forRole(Role::Patient)->create([
            'name' => 'Single study',
            'description' => 'Upload one more study and share it with your doctors.',
            'price' => 1000,
            'duration_days' => 30,
            'max_uploads' => 1,
            'max_storage_mb' => 2048,
            'max_reports' => 0,
            'max_shares' => 5,
            'max_guest_links' => 5,
        ]);

        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        foreach ([Role::Physician, Role::Technician, Role::Patient] as $role) {
            $user = User::create([
                'name' => "{$role->label()} User",
                'email' => "{$role->value}@example.com",
                'role' => $role,
                'password' => 'password',
            ]);

            if ($user->isPhysician()) {
                $user->markPhysicianVerified();
            }

            $subscriptions->startTrial($user);
        }
    }
}
