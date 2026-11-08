<?php

namespace Database\Factories;

use App\Models\CommissionPayout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionPayout>
 */
class CommissionPayoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'officer_id' => User::factory()->marketingOfficer(),
            'officer_name' => fake()->name(),
            'amount' => 1000,
            'payment_method' => 'JazzCash',
            'payment_reference' => fake()->numerify('TXN######'),
            'paid_at' => now(),
        ];
    }
}
