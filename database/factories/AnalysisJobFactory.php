<?php

namespace Database\Factories;

use App\Models\AnalysisJob;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AnalysisJob>
 */
class AnalysisJobFactory extends Factory
{
    protected $model = AnalysisJob::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalTexts = $this->faker->numberBetween(10, 100);
        $processedTexts = $this->faker->numberBetween(0, $totalTexts);
        
        return [
            'job_id' => Str::uuid(),
            'status' => $this->faker->randomElement([
                AnalysisJob::STATUS_PENDING,
                AnalysisJob::STATUS_PROCESSING,
                AnalysisJob::STATUS_COMPLETED,
                AnalysisJob::STATUS_FAILED
            ]),
            'total_texts' => $totalTexts,
            'processed_texts' => $processedTexts,
            'error_message' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalysisJob::STATUS_PENDING,
            'processed_texts' => 0,
            'error_message' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalysisJob::STATUS_PROCESSING,
            'processed_texts' => $this->faker->numberBetween(1, $attributes['total_texts'] - 1),
            'error_message' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalysisJob::STATUS_COMPLETED,
            'processed_texts' => $attributes['total_texts'],
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalysisJob::STATUS_FAILED,
            'processed_texts' => $this->faker->numberBetween(0, $attributes['total_texts'] / 2),
            'error_message' => $this->faker->sentence(),
        ]);
    }

    // Experiment functionality has been removed

    public function withTexts(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'total_texts' => $count,
            'processed_texts' => min($attributes['processed_texts'], $count),
        ]);
    }
}
