<?php

namespace Database\Factories;

use App\Enums\RiskFactor;
use App\Enums\Sex;
use App\Models\PatientRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientRecord>
 */
class PatientRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->physician(),
            'name' => fake()->name(),
            'medical_record_number' => fake()->numerify('MRN-#####'),
            'date_of_birth' => fake()->date(max: '-30 years'),
            'sex' => fake()->randomElement(Sex::cases()),
            'phone' => fake()->numerify('03#########'),
            'risk_factors' => [RiskFactor::Diabetes->value],
            'diagnosis' => fake()->sentence(),
            'history' => fake()->sentence(),
        ];
    }
}
