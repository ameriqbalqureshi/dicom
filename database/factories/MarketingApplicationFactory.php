<?php

namespace Database\Factories;

use App\Models\MarketingApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketingApplication>
 */
class MarketingApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'city' => fake()->city(),
            'experience' => fake()->sentence(),
        ];
    }
}
