<?php

namespace Database\Factories;

use App\Models\DicomInstance;
use App\Models\Series;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DicomInstance>
 */
class DicomInstanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'series_id' => Series::factory(),
            'sop_instance_uid' => '1.2.826.0.3.'.fake()->unique()->numerify('#########.#####'),
            'sop_class_uid' => '1.2.840.10008.5.1.4.1.1.2',
            'transfer_syntax_uid' => '1.2.840.10008.1.2.1',
            'instance_number' => fake()->numberBetween(1, 200),
            'rows' => 512,
            'columns' => 512,
            'number_of_frames' => 1,
            'file_path' => 'dicom/'.fake()->uuid().'.dcm',
            'file_size' => fake()->numberBetween(100_000, 600_000),
        ];
    }
}
