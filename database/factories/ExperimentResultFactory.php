<?php

namespace Database\Factories;

use App\Models\ExperimentResult;
use App\Models\Experiment;
use App\Models\AnalysisJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExperimentResult>
 */
class ExperimentResultFactory extends Factory
{
    protected $model = ExperimentResult::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'experiment_id' => Experiment::factory(),
            'analysis_job_id' => AnalysisJob::factory(),
            'llm_model' => $this->faker->randomElement(['claude-4', 'gemini-2.5-pro', 'gpt-4.1']),
            'metrics' => $this->generateMetrics(),
            'raw_results' => $this->generateRawResults(),
            'execution_time' => $this->faker->randomFloat(3, 0.5, 10.0),
        ];
    }

    public function forExperiment(Experiment $experiment): static
    {
        return $this->state(fn (array $attributes) => [
            'experiment_id' => $experiment->id,
        ]);
    }

    public function claude(): static
    {
        return $this->state(fn (array $attributes) => [
            'llm_model' => 'claude-4',
            'execution_time' => $this->faker->randomFloat(3, 1.0, 3.0),
        ]);
    }

    public function gemini(): static
    {
        return $this->state(fn (array $attributes) => [
            'llm_model' => 'gemini-2.5-pro',
            'execution_time' => $this->faker->randomFloat(3, 0.5, 2.0),
        ]);
    }

    public function openai(): static
    {
        return $this->state(fn (array $attributes) => [
            'llm_model' => 'gpt-4.1',
            'execution_time' => $this->faker->randomFloat(3, 1.5, 4.0),
        ]);
    }

    public function highAccuracy(): static
    {
        return $this->state(fn (array $attributes) => [
            'metrics' => [
                'precision' => $this->faker->randomFloat(3, 0.8, 0.95),
                'recall' => $this->faker->randomFloat(3, 0.75, 0.9),
                'f1_score' => $this->faker->randomFloat(3, 0.8, 0.92),
                'cohens_kappa' => $this->faker->randomFloat(3, 0.7, 0.85),
                'accuracy' => $this->faker->randomFloat(3, 0.85, 0.95),
            ],
        ]);
    }

    public function lowAccuracy(): static
    {
        return $this->state(fn (array $attributes) => [
            'metrics' => [
                'precision' => $this->faker->randomFloat(3, 0.3, 0.6),
                'recall' => $this->faker->randomFloat(3, 0.2, 0.5),
                'f1_score' => $this->faker->randomFloat(3, 0.25, 0.55),
                'cohens_kappa' => $this->faker->randomFloat(3, 0.1, 0.4),
                'accuracy' => $this->faker->randomFloat(3, 0.4, 0.65),
            ],
        ]);
    }

    private function generateMetrics(): array
    {
        $precision = $this->faker->randomFloat(3, 0.4, 0.9);
        $recall = $this->faker->randomFloat(3, 0.3, 0.85);
        $f1 = 2 * ($precision * $recall) / ($precision + $recall);
        
        return [
            'precision' => $precision,
            'recall' => $recall,
            'f1_score' => round($f1, 3),
            'cohens_kappa' => $this->faker->randomFloat(3, 0.2, 0.8),
            'accuracy' => $this->faker->randomFloat(3, 0.5, 0.9),
        ];
    }

    private function generateRawResults(): array
    {
        return [
            'primaryChoice' => [
                'choices' => [$this->faker->randomElement(['yes', 'no'])]
            ],
            'annotations' => $this->generateAnnotations(),
            'desinformationTechnique' => [
                'choices' => $this->faker->randomElements([
                    'emotional_appeal', 'false_authority', 'polarization', 
                    'misinformation', 'logical_fallacy'
                ], $this->faker->numberBetween(0, 3))
            ]
        ];
    }

    private function generateAnnotations(): array
    {
        $annotations = [];
        $count = $this->faker->numberBetween(0, 5);
        
        for ($i = 0; $i < $count; $i++) {
            $start = $this->faker->numberBetween(0, 100);
            $end = $start + $this->faker->numberBetween(10, 50);
            
            $annotations[] = [
                'type' => 'labels',
                'value' => [
                    'start' => $start,
                    'end' => $end,
                    'text' => $this->faker->sentence(),
                    'labels' => $this->faker->randomElements([
                        'emotional_appeal', 'false_authority', 'polarization'
                    ], $this->faker->numberBetween(1, 2))
                ]
            ];
        }
        
        return $annotations;
    }
}
