<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with one account per role.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        User::factory()->radiologist()->create([
            'name' => 'Radiologist User',
            'email' => 'radiologist@example.com',
        ]);

        User::factory()->technician()->create([
            'name' => 'Technician User',
            'email' => 'technician@example.com',
        ]);
    }
}
