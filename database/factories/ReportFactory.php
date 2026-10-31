<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\Study;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state: a draft by a physician who owns the study.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'study_id' => Study::factory()->for(User::factory()->physician(), 'uploader'),
            'author_id' => fn (array $attributes) => Study::find($attributes['study_id'])->uploaded_by,
            'indication' => fake()->sentence(),
            'findings' => fake()->paragraph(),
            'impression' => fake()->sentence(),
        ];
    }

    public function final(): static
    {
        return $this->afterCreating(fn (Report $report) => $report->finalize());
    }
}
