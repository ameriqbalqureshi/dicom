<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\MarketingProfile;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

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
            'email_verified_at' => now(),
            'role' => Role::Physician,
            'physician_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Give every user except administrators an active, unlimited package, so they are not held back by package limits
     * unless a test sets them.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->usesPackages()) {
                Subscription::factory()->for($user)->create();
            }
        });
    }

    /**
     * Indicate that the user has no package at all.
     */
    public function withoutPackage(): static
    {
        return $this->afterCreating(fn (User $user) => $user->subscriptions()->delete());
    }

    /**
     * Indicate that the user's package has the given limits, e.g. ['max_uploads' => 1].
     *
     * @param  array<string, int|null>  $limits
     */
    public function withLimits(array $limits): static
    {
        return $this->afterCreating(fn (User $user) => $user->subscriptions()->update($limits));
    }

    /**
     * Indicate that the user is a physician who signed up and has not been verified yet.
     */
    public function unverifiedPhysician(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Physician,
            'physician_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Admin,
        ]);
    }

    /**
     * Indicate that the user is a working marketing officer, with a referral code.
     */
    public function marketingOfficer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::MarketingOfficer,
        ])->afterCreating(fn (User $user) => MarketingProfile::factory()->for($user)->create());
    }

    public function physician(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Physician,
        ]);
    }

    public function technician(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Technician,
        ]);
    }

    public function patient(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => Role::Patient,
        ]);
    }
}
