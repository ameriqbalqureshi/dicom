<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state: an active, unlimited subscription that started a day ago.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->withoutPackage(),
            'package_id' => Package::factory(),
            'status' => SubscriptionStatus::Active,
            'amount' => 0,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
        ];
    }

    /**
     * Set limits on the subscription, e.g. ['max_uploads' => 1].
     *
     * @param  array<string, int|null>  $limits
     */
    public function withLimits(array $limits): static
    {
        return $this->state(fn (array $attributes) => $limits);
    }

    /**
     * A purchase waiting for payment confirmation.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::Pending,
            'amount' => 5000,
            'payment_method' => 'Test Bank',
            'payment_reference' => fake()->bothify('TXN-#######'),
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subDay(),
        ]);
    }
}
