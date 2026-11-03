<?php

namespace Database\Factories;

use App\Models\Study;
use App\Models\StudyComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudyComment>
 */
class StudyCommentFactory extends Factory
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
            'author_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
