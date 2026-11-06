<?php

namespace Database\Factories;

use App\Models\DicomArchive;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DicomArchive>
 */
class DicomArchiveFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uploaded_by' => User::factory()->technician(),
            'original_name' => 'study.zip',
            'file_path' => 'archives/'.fake()->uuid().'.zip',
            'file_size' => 1_000_000,
            'uploaded_at' => now(),
        ];
    }

    /**
     * An archive whose bytes have not finished uploading.
     */
    public function pending(): static
    {
        return $this->state(['uploaded_at' => null]);
    }
}
