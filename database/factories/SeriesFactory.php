<?php

namespace Database\Factories;

use App\Models\Series;
use App\Models\Study;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Series>
 */
class SeriesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_id' => Study::factory(),
            'series_instance_uid' => '1.2.826.0.2.'.fake()->unique()->numerify('#########.#####'),
            'modality' => fake()->randomElement(['CT', 'MR', 'CR', 'US']),
            'series_number' => fake()->numberBetween(1, 20),
            'series_description' => fake()->words(2, true),
        ];
    }
}
