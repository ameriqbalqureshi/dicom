<?php

namespace Database\Factories;

use App\Models\MarketingProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketingProfile>
 */
class MarketingProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'referral_code' => Str::upper(Str::random(10)),
        ];
    }

    /**
     * An officer who has been deactivated.
     */
    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => ['deactivated_at' => now()]);
    }
}
