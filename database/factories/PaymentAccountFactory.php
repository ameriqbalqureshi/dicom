<?php

namespace Database\Factories;

use App\Enums\PaymentAccountType;
use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAccount>
 */
class PaymentAccountFactory extends Factory
{
    /**
     * Define the model's default state: an active bank account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Bank',
            'type' => PaymentAccountType::Bank,
            'account_title' => fake()->company(),
            'account_number' => fake()->numerify('##############'),
            'iban' => fake()->numerify('PK##TEST################'),
            'branch' => fake()->city(),
            'is_active' => true,
        ];
    }

    public function mobileWallet(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->unique()->word().' wallet',
            'type' => PaymentAccountType::MobileWallet,
            'account_number' => fake()->numerify('03#########'),
            'iban' => null,
            'branch' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
