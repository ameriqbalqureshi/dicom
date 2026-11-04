<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state: an active, paid, unlimited package for physicians lasting 30 days.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'role' => Role::Physician,
            'price' => 5000,
            'duration_days' => 30,
            'max_uploads' => null,
            'max_patient_records' => null,
            'max_storage_mb' => null,
            'max_reports' => null,
            'max_shares' => null,
            'max_guest_links' => null,
            'is_trial' => false,
            'is_active' => true,
        ];
    }

    public function forRole(Role $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Free trial',
            'price' => 0,
            'is_trial' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
