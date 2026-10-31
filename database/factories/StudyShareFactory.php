<?php

namespace Database\Factories;

use App\Models\Study;
use App\Models\StudyShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyShare>
 */
class StudyShareFactory extends Factory
{
    /**
     * Define the model's default state: an accepted share from the study's owner to a physician.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_id' => Study::factory(),
            'user_id' => User::factory()->physician(),
            'shared_by' => fn (array $attributes) => Study::find($attributes['study_id'])->uploaded_by,
            'accepted_at' => now(),
        ];
    }

    /**
     * Indicate that the recipient has not accepted the share yet.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => null,
        ]);
    }
}
