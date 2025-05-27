<?php

namespace Database\Factories;

use App\Models\ComparisonMetric;
use App\Models\AnalysisJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComparisonMetric>
 */
class ComparisonMetricFactory extends Factory
{
    protected $model = ComparisonMetric::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $truePositives = $this->faker->numberBetween(0, 10);
        $falsePositives = $this->faker->numberBetween(0, 5);
        $falseNegatives = $this->faker->numberBetween(0, 5);
        
        return [
            'job_id' => AnalysisJob::factory(),
            'text_id' => $this->faker->numberBetween(1, 1000),
            'model_name' => $this->faker->randomElement(['claude-4', 'gemini-2.5-pro', 'gpt-4.1']),
            'true_positives' => $truePositives,
            'false_positives' => $falsePositives,
            'false_negatives' => $falseNegatives,
            'position_accuracy' => $this->faker->randomFloat(3, 0.0, 1.0),
        ];
    }

    public function forJob(AnalysisJob $job): static
    {
        return $this->state(fn (array $attributes) => [
            'job_id' => $job->job_id,
        ]);
    }

    public function forText(string $textId): static
    {
        return $this->state(fn (array $attributes) => [
            'text_id' => $textId,
        ]);
    }

    public function claude(): static
    {
        return $this->state(fn (array $attributes) => [
            'model_name' => 'claude-4',
        ]);
    }

    public function gemini(): static
    {
        return $this->state(fn (array $attributes) => [
            'model_name' => 'gemini-2.5-pro',
        ]);
    }

    public function openai(): static
    {
        return $this->state(fn (array $attributes) => [
            'model_name' => 'gpt-4.1',
        ]);
    }

    public function perfectMatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'true_positives' => 5,
            'false_positives' => 0,
            'false_negatives' => 0,
            'position_accuracy' => 1.0,
        ]);
    }

    public function noMatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'true_positives' => 0,
            'false_positives' => 3,
            'false_negatives' => 4,
            'position_accuracy' => 0.0,
        ]);
    }

    public function partialMatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'true_positives' => 2,
            'false_positives' => 1,
            'false_negatives' => 2,
            'position_accuracy' => 0.75,
        ]);
    }

    public function highAccuracy(): static
    {
        return $this->state(fn (array $attributes) => [
            'true_positives' => $this->faker->numberBetween(7, 10),
            'false_positives' => $this->faker->numberBetween(0, 1),
            'false_negatives' => $this->faker->numberBetween(0, 1),
            'position_accuracy' => $this->faker->randomFloat(3, 0.9, 1.0),
        ]);
    }

    public function lowAccuracy(): static
    {
        return $this->state(fn (array $attributes) => [
            'true_positives' => $this->faker->numberBetween(0, 2),
            'false_positives' => $this->faker->numberBetween(3, 7),
            'false_negatives' => $this->faker->numberBetween(3, 7),
            'position_accuracy' => $this->faker->randomFloat(3, 0.0, 0.3),
        ]);
    }
}