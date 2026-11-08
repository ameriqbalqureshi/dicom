<?php

namespace Database\Factories;

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Enums\Role;
use App\Models\Commission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commission>
 */
class CommissionFactory extends Factory
{
    /**
     * Define the model's default state: a first-payment commission whose hold has ended, so it can be paid.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'officer_id' => User::factory()->marketingOfficer(),
            'officer_name' => fake()->name(),
            'client_name' => fake()->name(),
            'client_role' => Role::Physician,
            'type' => CommissionType::FirstPayment,
            'status' => CommissionStatus::Pending,
            'base_amount' => 5000,
            'rate_percent' => 20,
            'amount' => 1000,
            'available_at' => now()->subDay(),
        ];
    }

    /**
     * A commission still within its hold.
     */
    public function onHold(): static
    {
        return $this->state(fn (array $attributes) => ['available_at' => now()->addDays(14)]);
    }

    /**
     * A commission that has been paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => ['status' => CommissionStatus::Paid]);
    }
}
