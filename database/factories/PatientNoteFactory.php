<?php

namespace Database\Factories;

use App\Models\PatientNote;
use App\Models\PatientRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientNote>
 */
class PatientNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_record_id' => PatientRecord::factory(),
            'noted_on' => now()->toDateString(),
            'body' => fake()->sentence(),
        ];
    }
}
