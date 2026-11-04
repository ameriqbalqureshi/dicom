<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\PaymentSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentSubmission>
 */
class PaymentSubmissionFactory extends Factory
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
            'account_identifier' => fake()->safeEmail(),
            'package_id' => Package::factory(),
            'payment_method' => 'Test Bank',
            'payment_reference' => fake()->bothify('TXN-#######'),
            'receipt_path' => 'receipts/'.fake()->uuid().'.jpg',
        ];
    }
}
