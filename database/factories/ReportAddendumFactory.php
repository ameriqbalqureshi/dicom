<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportAddendum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportAddendum>
 */
class ReportAddendumFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory()->final(),
            'body' => fake()->sentence(),
        ];
    }
}
