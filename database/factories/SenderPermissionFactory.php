<?php

namespace Database\Factories;

use App\Enums\SenderStatus;
use App\Models\SenderPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SenderPermission>
 */
class SenderPermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipient_id' => User::factory()->physician(),
            'sender_id' => User::factory(),
            'status' => SenderStatus::Allowed,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SenderStatus::Suspended,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SenderStatus::Blocked,
        ]);
    }
}
