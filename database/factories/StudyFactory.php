<?php

namespace Database\Factories;

use App\Models\Study;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Study>
 */
class StudyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_instance_uid' => '1.2.826.0.1.'.fake()->unique()->numerify('#########.#####'),
            'patient_name' => fake()->name(),
            'patient_id' => fake()->unique()->numerify('PID######'),
            'patient_birth_date' => fake()->date(),
            'patient_sex' => fake()->randomElement(['M', 'F', 'O']),
            'study_date' => fake()->dateTimeBetween('-2 years'),
            'study_description' => fake()->randomElement(['CT Chest', 'MR Brain', 'CR Chest PA', 'US Abdomen']),
            'accession_number' => fake()->numerify('ACC#######'),
            'uploaded_by' => User::factory(),
        ];
    }
}
