<?php

namespace Database\Factories;

use App\Models\PatientRecord;
use App\Models\PatientRecordStudy;
use App\Models\Study;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientRecordStudy>
 */
class PatientRecordStudyFactory extends Factory
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
            'study_id' => Study::factory(),
            'study_date' => fake()->date(),
            'study_description' => 'Coronary angiogram',
        ];
    }
}
