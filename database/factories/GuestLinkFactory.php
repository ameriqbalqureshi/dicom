<?php

namespace Database\Factories;

use App\Models\GuestLink;
use App\Models\Study;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<GuestLink>
 */
class GuestLinkFactory extends Factory
{
    /**
     * Define the model's default state: an active link without a PIN, created by the study's owner.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_id' => Study::factory()->for(User::factory()->physician(), 'uploader'),
            'created_by' => fn (array $attributes) => Study::find($attributes['study_id'])->uploaded_by,
            'token_hash' => GuestLink::hashToken(Str::random(48)),
            'pin_hash' => null,
            'expires_at' => now()->addHours(GuestLink::LIFETIME_HOURS),
        ];
    }

    /**
     * Use a known token, so tests can open the link.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'token_hash' => GuestLink::hashToken($token),
        ]);
    }

    public function withPin(string $pin): static
    {
        return $this->state(fn (array $attributes) => [
            'pin_hash' => Hash::make($pin),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }
}
